### Unreleased

* Fix - the /tests directory was badly configured in the gitattributes and not actually excluded from the package.

* Add a ConsistentStringScrambler - Consistently "randomise" the words in a string to be the same for the same random seed value using a Seeded Fisher-Yates shuffle

* Add classes to simplify the creation and parsing of XML sitemaps

* Add method to factory a DateTimeImmutable from a strict format and throw if it doesn't comply

* Add TemporaryDirectoryManager to simplify the creation and destruction of temporary directories

### v1.17.2 (2022-10-31)

* Update `JSON::decode` to throw an explicit exception on NULL input
  This has always actually thrown an InvalidJSONException, but it used to be indistinguishable
  from the `Syntax error` produced by empty/invalid JSON strings.

* Revert change in v1.16.0 that caught all `ErrorException` in `JSON::decode` - these will now
  bubble as previously.

* Fix tests for StrictDate::date_after

* Deprecate all StrictDate:: validation methods related to the deprecated `InvalidUserDateTime` object - validate
  date inputs either as strings *or* as (valid) date objects, not both.

### v1.17.1 (2022-10-28)

* Fix deprecation warning when passing NULL to date validator by casting 
  to empty string to maintain current behaviour.

* Make DbBackedMutexWrapper locking type-safe across PHP versions

### v1.17.0 (2022-10-14)

* Support PHP 8.2

### v1.16.0 (2022-10-10)

* Support PHP 8.1
* Drop support for PHP 7.4

### v1.15.0 (2021-11-24)

* Optionally report the count of log entries to a MetricsAgent- see `StackdriverApplicationLogger::withMetrics()`

### v1.14.1 (2021-11-22)

* [BUG] Request memory usage logs introduced in 1.14 should have been reporting the "real" memory usage
  e.g. `memory_get_peak_usage(TRUE)` rather than the less reliable emalloc based allocations php returns by
  default.

### v1.14.0 (2021-11-19)

* StackdriverApplicationLogger: limit length of user-agent string in request logs to a maximum of 500 bytes.
* Now requires the `mbstring` PHP extension to be present.
* StackdriverApplicationLogger: add the peak memory usage to the context info of the request logger (context.mem_mb).

### v1.13.0 (2021-11-04)

* Add support to DateTimeImmutableFactory for creation from common 'Y-m-d H:i:s' format
* Deprecate old DateTimeImmutableFactory methods for creating DateTime objects from invalid user formats

### v1.12.0 (2021-10-04)

* Extend MetricsAgent to handle additional metric types
* Add StatsiteAgent capable of formatting and sending UDP messages to [statsite](https://github.com/statsite/statsite)
* Add DateTimeDiff::microsBetween() to calculate the exact number of microseconds between two DateTimeImmutable values without any risk of floating point precision errors

### v1.11.0 (2021-04-19)

* Support PHP8

### v1.10.0 (2021-04-09)

* Thin interface for running a block of code inside an explicit database transaction
  Provides a null implementation for use in testing, and a Doctrine2 implementation that can be used
  if you're using Doctrine2 in your project. Alternatively you can implement your own wrapper as required.

### v1.9.0 (2021-03-16)

* Barebones metrics interface which will be fleshed out in a further release
* OperationTimer for capturing timer metrics
* Correct @license tags

### v1.8.0 (2021-03-15)

* [CAUTION] StoppedMockClock now stores / returns fractional seconds for consistency with real DateTimeImmutable
  PHP DateTime objects now always carry microseconds - the StoppedMockClock should therefore always include them
  in the times it accepts and returns. There is a possibility this will cause some strict equality checks in
  unit tests to fail. Not treated as a package breaking release as it only affects testcase code.
* Add ObjectPropertyRipper::ripAll to grab all variables (from simple objects with no private props in
  parent classes).

### v1.7.0 (2020-11-16)

* Add DateTimeImmutableFactory and DateString methods for dealing with microsecond-precision 
  date/time values
* Improve test assertion API on the MockMutexWrapper

### v1.6.0 (2020-10-29)

* Support php^7.4
* Use new setcookie() signature

### v1.5.0 (2020-10-29)

* Stable release of beta3 (no changes)

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
