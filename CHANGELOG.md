### Unreleased

### v1.5.0-beta3 (2020-10-09)

* Init the DeviceIdentifier to a fixed value in the CLI environment without setting any cookies, to prevent
  errors if the process has already sent output.
* Add MutexWrapper with Mock and Db (mysql) backed implementations for preventing concurrent executions
  of code.
 
### v1.5.0-beta2 (2020-09-24)

* [BREAKING] Removed the $session_id parameter from `DefaultLogMetadata::requestTrace` - use the new
  `DefaultLogMetadata::deviceIdentityLazy` method instead to capture the device ID into the logs.
  The session ID was removed because reading from $_COOKIE does not provide a value on the user's first
  request, and other methods (e.g. session_id()) may not give an accurate value if the session cookie name
  is customised but the session has not been started at the time of logging. 
* Add class to assign users a device ID cookie and provide the value for logging
* Add class to wrap accessing / setting / deleting cookies for injectability and testability
* Add helper method to get a DateTimeImmutable from a unix timestamp, in current timezone

### v1.5.0-beta1 (2020-05-14)

* Add AssociativeArrayUtils for common operations on associative arrays
* Add InitialisableSingleonTrait for objects that need to be initialised as singletons
* Add StackdriverApplicationLogger framework and supporting classes

### v1.4.2 (2020-02-18)

* Fix fatal error on updateSessionTimestamp due to incorrect variable naming. Made all vars consistent as 
  $session_id to avoid recurrence.

### v1.4.1 (2020-02-17)

* Fix missing return type from releaseLock when no lock is held

### v1.4.0 (2020-02-17)

* Update MysqlSession to use strict sessions, SessionIdInterface and SessionUpdateTimestampHandlerInterface
  The updated hander solves a couple of edge cases where the session data could be written but not read if using the
  wrong hash. This would for example occur if an attacker attempted to overwrite and existing session, or if the hash
  salt changed during a user's session. The new handler uses strict session mode and custom handler logic to validate
  the session ID, including checking the hash, and issues a new session ID if it is invalid. The updated logic is also
  more performant at the database as sessions are only INSERTed on creation and subsequently UPDATEd, rather than
  the previous INSERT...ON DUPLICATE KEY UPDATE. **Note that ->initialise() now sets the session.use_strict_mode ini
  value as it is required for proper operation. This should be set anyway, and is only relevant to the handler (of
  which there can be only one) so this is not considered to be true global state or a breaking change.


### v1.3.0 (2020-01-16)

* Add StaticAssetUrlProvider to provide simple cache-busted local URLs for CSS etc in local
  dev or remote (e.g. cloud storage / s3) urls in production.

### v1.2.1 (2019-11-15)

* Allow DeploymentConfig->map() to return values in standalone environment
  This brings the `standalone` closer to the behaviour of other environments, except that it will
  continue to return null if there is nothing mapped (where other environments will throw). `->read`
  continues to return null in standalone in every case. Note that standalone will now return a value
  if there's one mapped for `any` (`*`) - which is a minor breaking change to the behaviour of the 
  standalone environment.

### v1.2.0 (2019-11-12)

* Add Base64Url StringEncoding helper class - like base64, but with entirely websafe characters for URLs etc
* Add JSON StringEncoding helper class - safe json parsing, encoding and prettifying with sane defaults
* Add DeploymentConfig sub-package for loading (and, optionally, decrypting) runtime environment configuration

### v1.1.0 (2019-07-09)

* Allow asserting that the StoppedMockClock never slept

### v1.0.0 (2019-04-03)

* First major release from 0.2.0

### v0.2.0 (2019-04-02)

* Drop support for php < 7.2
* Run test suite against php 7.2

### v0.1.6 (2019-03-18)

* Update StoppedMockClock to support newer phpunit (use namespaced assert class) and add
  unit tests.

### v0.1.6 (2018-09-06)

* Add AbstractArrayRepository

### v0.1.5(2018-08-16)

* Add MysqlSession session handler

### v0.1.4 (2018-04-30)

* Add StrictDate::on_or_after for validating date >= date ignoring any time component

### v0.1.3 (2018-02-22)

* Extract the basic ValidNumber class for validating minimum

### v0.1.2 (2018-02-13)

* Fix signature of InvalidUserDateTime::createFromMutable for PHP >= 5.6 compatibility

### v0.1.1 (2018-02-09)
* Add RealtimeClock and StoppedMockClock testing wrapper

### v0.1.0 (2018-02-09)

* First version
