### Unreleased

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
