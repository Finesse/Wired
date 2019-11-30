# Wired

[![Latest Stable Version](https://poser.pugx.org/finesse/wired/v/stable)](https://packagist.org/packages/finesse/wired)
[![Total Downloads](https://poser.pugx.org/finesse/wired/downloads)](https://packagist.org/packages/finesse/wired)
![PHP from Packagist](https://img.shields.io/packagist/php-v/finesse/wired.svg)
[![Test Status](https://github.com/finesse/Wired/workflows/Test/badge.svg)](https://github.com/Finesse/Wired/actions?workflow=Test)
[![Maintainability](https://api.codeclimate.com/v1/badges/d3f2ac1293709f054302/maintainability)](https://codeclimate.com/github/Finesse/Wired/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/d3f2ac1293709f054302/test_coverage)](https://codeclimate.com/github/Finesse/Wired/test_coverage)

Wired is an ORM for PHP.

Key features:

* Simple configuration and usage. Only a database connection data (host, login, etc.) and models classes are required.
* Light itself, uses light dependencies that can be used separately (e.g.
    [query builder](https://github.com/Finesse/QueryScribe), [database connector](https://github.com/Finesse/MicroDB)).
* Not a part of a framework.
* Supports table prefixes.
* No static facades, only explicit delivery using dependency injection.
* Has a query builder with powerful features like nested queries.
* Exceptions on errors.

Supported DBMSs:

* MySQL
* SQLite
* PostrgeSQL (partially, see [the issue](https://github.com/Finesse/MicroDB#known-problems))

If you need a new database system support please implement it [there](https://github.com/Finesse/MicroDB) and 
[there](https://github.com/Finesse/QueryScribe) using pull requests.


## Documentation

The documentation is available at [wired-orm.readthedocs.io](http://wired-orm.readthedocs.io).

Also all the classes, methods and properties has a PHPDoc comment in the code.


## Versions compatibility

The project follows the [Semantic Versioning](http://semver.org).


## License

MIT. See [the LICENSE](LICENSE) file for details.
