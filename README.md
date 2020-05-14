php-utils provides common, simple, dependency-free PHP helpers

[![Build Status](https://travis-ci.org/ingenerator/php-utils.svg?branch=1.0.x)](https://travis-ci.org/ingenerator/php-utils)


# Installing php-utils

This isn't in packagist yet : you'll need to add our package repository to your composer.json:

```json
{
  "repositories": [
    {"type": "composer", "url": "https://php-packages.ingenerator.com"}
  ]
}
```

`$> composer require ingenerator/php-utils`

# Functionality included

* [Support for managing runtime/environment configuration](docs/managing_runtime_config.md)
* Logging framework for outputting application and request logs to Stackdriver via stderr/stdout (see 
  StackdriverApplicationLogger)
* Utils for working with Associative Arrays (some ported from Kohana framework)

# Contributing

Contributions are welcome but please contact us before you start work on anything :
this is primarily an internally-focused package so we may have particular requirements
/ opinions that differ from yours. 

# Contributors

This package has been sponsored by [inGenerator Ltd](http://www.ingenerator.com)

* Andrew Coulton [acoulton](https://github.com/acoulton) - Lead developer

# Licence

Licensed under the [BSD-3-Clause Licence](LICENSE)
