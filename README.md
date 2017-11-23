# Wired

[![Latest Stable Version](https://poser.pugx.org/finesse/wired/v/stable)](https://packagist.org/packages/finesse/wired)
[![Total Downloads](https://poser.pugx.org/finesse/wired/downloads)](https://packagist.org/packages/finesse/wired)
[![Build Status](https://php-eye.com/badge/finesse/wired/tested.svg)](https://travis-ci.org/FinesseRus/Wired)
[![Coverage Status](https://coveralls.io/repos/github/FinesseRus/Wired/badge.svg?branch=master)](https://coveralls.io/github/FinesseRus/Wired?branch=master)
[![Dependency Status](https://www.versioneye.com/php/finesse:wired/badge)](https://www.versioneye.com/php/finesse:wired)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/57f28947-02f4-4623-9992-e403222a1b9d/mini.png)](https://insight.sensiolabs.com/projects/57f28947-02f4-4623-9992-e403222a1b9d)

Wired is an ORM for PHP.

Key features:

* Light itself, uses light dependencies.
* Not a part of a framework.
* Supports table prefixes.
* No external configuration (except database connection), models are configured in the models classes.
* No static facades, only explicit delivery using dependency injection.
* Has a query builder.
* Exceptions on errors.
* Build on top of small modules which can be used separately (e.g. 
  [query builder](https://github.com/FinesseRus/QueryScribe), 
  [database connector](https://github.com/FinesseRus/MicroDB)).

Supported DBMSs:

* MySQL
* SQLite
* PostrgeSQL (partially, see [the issue](https://github.com/FinesseRus/MicroDB#known-problems))

If you need a new database system support please implement it [there](https://github.com/FinesseRus/MicroDB) and 
[there](https://github.com/FinesseRus/QueryScribe) using pull requests.


## Documentation

The documentation is located in [the `docs` directory](docs/getting-started.md).


## Versions compatibility

The project follows the [Semantic Versioning](http://semver.org).


## License

MIT. See [the LICENSE](LICENSE) file for details.
