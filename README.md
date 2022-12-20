php-utils provides common, simple, dependency-free PHP helpers. Note that the selection and operation of utility functions is (and always will be) opinionated based on our needs.

[![Tests](https://github.com/ingenerator/php-utils/workflows/Run%20tests/badge.svg)](https://github.com/ingenerator/php-utils/actions)


# Installing php-utils

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
