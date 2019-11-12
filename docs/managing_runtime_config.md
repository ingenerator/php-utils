# Managing deployment/runtime configuration

php-utils offers a solution to the common problem of retrieving runtime configuration values, optionally handling 
variations between environments and protection of secrets that should not be committed directly to a repository. This
is primarily designed for use in a docker/kubernetes setup, although it can function just as effectively elsewhere.
The config management classes can read in values from files (e.g. mounted config maps / secrets) and/or from hardcoded
per-environment config maps built into the calling code.

Values - from external files or source code - can optionally be declared with encrypted content and decrypted at 
runtime.

The deployment config code can be used with a variety of application-level config readers.

**!! NOTE : CACHING** - the code in this package does not implement any caching of config values. This may cause
large numbers of file reads and decryption operations on every request. We strongly recommend implementing caching of
the overall compiled runtime configuration for your application. This should be handled at the application level.

### Defining the runtime environment

Often, configuration depends on whether the application is running on a local developer workstation / in CI / in staging
/ in production etc.

We determine this based on the value of an `INGENERATOR_ENV` environment variable, which should be declared by your
provisioning architecture. If the environment variable is missing, we default to `production` to avoid any risk of
development flags accidentally being enabled.

### Simple environment toggles

You can control behaviour of your app directly based on the environment:

```php
<?php
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig as Cfg;
// application/config.php
$cfg = Cfg::instance();
return [
    // NB - we generally recommend defining appropriately-named flags, rather than depending directly on the config
    // class throughout your app. This allows an easy overview of the variation between envs in your config files.
    'dev_toggles' => [
        'allow_fake_login' => $cfg->isEnvironment(Cfg::DEV),        
        'disable_payment' => $cfg->notEnvironment(Cfg::PRODUCTION),
        // isEnvironment and notEnvironment optionally take a list of environments too
        'force_ssl' => $cfg->notEnvironment(Cfg::DEV, Cfg::CI),
    ],
    // You can also use as simple ternaries (but we suggest using map instead, see below)
    'page_title' => $cfg->isEnvironment(Cfg::DEV) ? 'Hey Devs' : 'My awesome site',
];
```

### Mapping values for different environments

Sometimes you need more complex variations between different environments:

```php
<?php
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig as Cfg;
$cfg = Cfg::instance();

return [
    'api_to_cool_site' => [
        'base_url' => $cfg->map(
            // Specify a value for a single environment
            [Cfg::PRODUCTION, 'https://api.coolsite.com/'],
            // Specify a value for a list of environments
            [[Cfg::DEV, Cfg::CI], 'http://local.coolsite.simulator:9090/'],
            // Specify a fallback to be used if not defined above (note, without a fallback any undefined environment
            // will throw an exception.
            [Cfg::ANY, 'https://sandbox.coolsite.com/']
        ),
    ], 
];
```

### Reading string values from files

You may need to read values from files - for example, credentials or hostnames for other services running within a
kubernetes cluster.
 
For example:

```php
<?php
// application/config.php
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig;

$cfg = DeploymentConfig::instance();
return [
    'database' => [
        'hostname' => $cfg->read('database/hostname.cfg'),
        'username' => 'myapp',
        'password' => $cfg->read('database/password.cfg')
    ]
];
```

Config files are by default located at `/etc/ingenerator/conf/` - you can customise this by extending the 
DeploymentConfig class and overriding the `$config_dir` property.

### Reading complex values from files

`->read()` always returns a string. Sometimes you need to provide a type-sensitive result (e.g. an integer), or a
nested array of values. `->readJSON()` works equivalent to `read()` but expects to find something that can be decoded
as valid JSON - and will throw if it cannot be.

## Encrypted configuration values
Encrypted configuration values are protected with asymmetric encryption using the sodium Crypto Box
algorithm. This allows developers to have access to the public key (for adding new values) while 
enforcing stricter controls on decrypting existing configs.

You can have as many keypairs as you wish - for example, you may need semi-secure storage of 
credentials for third-party sandbox APIs that are needed for development or integration testing,
and more restricted storage of the credentials for the production system.

Encrypted config values can be defined in standalone files (accessed with `->read()`) or as string values passed in to 
`->map()`. They are stored in the format `#SECRET{-$keypair_name}#{base64_encoded_value}` and recognised by pattern
matching.

### Initialising a master encryption keypair

To create a keypair:

```php
$keypair = sodium_crypto_box_keypair();
$public  = sodium_crypto_box_publickey($keypair);

// write the public key where a developer can access it
file_put_contents('config/default.secret-config.pub', base64_encode($public));

// write the private key through a suitable encryption algorithm e.g. google KMS
// note the package expects eventually to get the keypair in base64 format
$keypair   = base64_encode($keypair); 
$encrypted = encrypt_my_value_sensibly($keypair);
file_put_contents('config/default.secret-config.key.enc', $encrypted);
```

By default at runtime, keys are expected to be at `{config_dir}/app_config_keys/{name}.secret-config.key`. Your 
provisioning should therefore decrypt `config/default.secret-config.key.enc` and place it in the expected location.

### Encrypting a configuration value

After generating a keypair, you can use it to encrypt a configuration value. This can be copy/pasted into a config file
or saved standalone, as required.

```php
$my_api_password = 'letmein';
$key_name        = 'default';
$public_key      = base64_decode(file_get_contents('config/'.$key_name.'.secret-config.pub'));
$cipher          = sodium_crypto_box_seal($my_api_password, $public_key);
$config_val      = '#SECRET-'.$key_name.'#'.base64_encode($cipher);
echo $config_val;
```

## The `standalone` environment

By default, the deployment config class will throw an exception if it cannot find the file specified in a `read()` call,
or the private key to decrypt an encrypted config. This guards against errors in deployment/provisioning code that might
otherwise leave the application with unexpected invalid config. 

Sometimes, however, you need to boot the app without defining all the required config. For example, to run unit tests
or console helper scripts that do not need to actually provide a full set of "live" database configurations.

For this case, we support the `standalone` environment. In `standalone`, all calls to `->read()` and `->map()` will 
return `NULL`. For example, to run unit tests:

`INGENERATOR_ENV=standalone bin/phpunit`

## A complete config file example
```php
<?php
// application/config.php
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig as Cfg;
$cfg = Cfg::instance();
return [
    'database' => [
        // Loads plaintext value from /etc/ingenerator/conf/database/hostname.cfg
        'hostname' => $cfg->read('database/hostname.cfg'),
        // The username and password files contain an encrypted config string that is decrypted at runtime
        'username' => $cfg->read('database/username.cfg'),
        'password' => $cfg->read('database/password.cfg'),
        // The app expects to get this as an integer - the file just contains the string `3306` which json_decodes to int
        'port' => $cfg->readJSON('database/port.cfg')
    ],
    'sessions' => [
        'secure_cookies' => $cfg->notEnvironment(Cfg::DEV, Cfg::PRODUCTION),
        'signing_secret' => $cfg->map(
            [[Cfg::DEV, Cfg::CI], 'insecure-token'],
            [Cfg::ANY, '#SECRET-default#ab8123hasd12332534']
        ),       
    ]
];
```
