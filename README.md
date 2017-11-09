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


## Installation

You need [composer](https://getcomposer.org) to use this library. Run in a console:
                                                                  
```bash
composer require finesse/wired
```


## Configuration

Configuration is done once.

### Database connection

First you need to configure a connection to your database:

```php
use Finesse\Wired\Mapper;

$orm = Mapper::create([
    'driver'   => 'mysql',                     // DBMS type: 'mysql', 'sqlite' or anything else for other (optional) 
    'dsn'      => 'mysql:host=host;dbname=db', // PDO data source name (DSN)
    'username' => 'root',                      // Database username (optional)
    'password' => 'qwerty',                    // Database password (optional)
    'options'  => [],                          // PDO options (optional)
    'prefix'   => ''                           // Tables prefix (optional)
]);
```

See more about the PDO options at the [PDO constructor reference](http://php.net/manual/en/pdo.construct.php).

Alternatively you can create all the dependencies manually:

```php
use Finesse\MiniDB\Database;
use Finesse\Wired\Mapper;

$database = Database::create([/* config, see example above */]);

$orm = new Mapper($database);
```

You can get more information about creating a `Database` instance 
[there](https://github.com/FinesseRus/MiniDB#getting-started).

### Models

To make a model make a class anywhere which extends `Finesse\Wired\Model` or implements `Finesse\Wired\ModelInterface`.

```php
use Finesse\Wired\Model;

class User extends Model
{
    // User fields
    public $id;
    public $name;
    public $email;
    public $rank;

    // Returns the database table name where users are stored (not prefixed)
    public static function getTable(): string
    {
        return 'users';
    }
}
```

## Usage

### Retrieving models

Get a model by identifier:

```php
$user = $orm->model(User::class)->find(15); // A User instance of null
```

Get an array of models by identifiers:

```php
$users = $orm->model(User::class)->find([5, 27, 183]); // [User, User, User]
```

Get all models:

```php
$users = $orm->model(User::class)->get();
```

Get models with a clause:

```php
$importantUsers = $orm
    ->model(User::class)
    ->where('rank', '>', 10)
    ->orWhere('name', 'Boss')
    ->orderBy('rank', 'desc')
    ->limit(10)
    ->get();
```

You can find more cool examples of using the query builder 
[there](https://github.com/FinesseRus/QueryScribe#building-queries).

#### Pagination

We suggest [Pagerfanta](https://github.com/whiteoctober/Pagerfanta) to easily make pagination.

First install Pagerfanta using [composer](https://getcomposer.org) by running in a console:

```bash
composer require pagerfanta/pagerfanta
```

Then make a query from which models should be taken:

```php
$query = $orm
    ->model(User::class)
    ->where('rank', '>', 5)
    ->orderBy('rank', 'desc');
    // Don't call ->get() here
```

And use Pagerfanta:

```php
use Finesse\Wired\ThirdParty\PagerfantaAdapter;
use Pagerfanta\Pagerfanta;

$paginator = new Pagerfanta(new PagerfantaAdapter($query));
$paginator->setMaxPerPage(10); // The number of models on a page
$paginator->setCurrentPage(3); // The current page number

$currentPageRows = $paginator->getCurrentPageResults(); // The models for the current page
$pagesCount = $paginator->getNbPages();                 // Total pages count
$haveToPaginate = $paginator->haveToPaginate();         // Whether the number of models is higher than the max per page
```

You can find more reference and examples for Pagerfanta [there](https://github.com/whiteoctober/Pagerfanta#usage).

#### Chunking models

If you need to process a large amount of models you can use chunking. In this approach portions of models are retrieved 
from the database instead of retrieving all the models at once.

```php
$database
    ->model(User::class)
    ->orderBy('id')
    ->chunk(100, function ($users) {
        foreach ($users as $user) {
            // Process the user here
        }
    });
```

### Saving models

Add a new model to the database:

```php
$user = new User();
$user->name = 'Newbie';
$user->email = 'jack@example.com';
$user->rank = 1;

$orm->save($user);

echo 'Your ID is '.$user->id;
```

Change an existing model:

```php
$user = $orm->model(User::class)->find(14);
$user->name = 'Don';
$orm->save($user);
```

Save many models at once:

```php
$orm->save([$user1, $user2, $user3]);
```

### Deleting models

Delete a model object from the database:

```php
$orm->delete($user);
```

Delete many model objects at once:

```php
$orm->delete([$user1, $user2, $user3]);
```

Delete many models with a clause:

```php
$orm
    ->model(User::class)
    ->where('rank', '<', 0)
    ->orWhere('name', 'Cheater')
    ->delete();
```

### Model relations

#### One to many

Add a method to a model class to tell the ORM that a model instance has many related instances of one type:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\HasMany;


class User extends Model
{
    public $id;
    // ...

    public static function posts()
    {
        return new HasMany(Post::class, 'user_id');
    }

    // ...
}
```

The `posts` method name is the relation name. You can set any name. The relation above tells that the `Post` model has 
the `user_id` field which contains a user instance identifier.

If you need the `user_id` field to point to another `User` field, pass it's name to the third `HasMany` argument:

```php
return new HasMany(Post::class, 'user_email', 'email');
``` 

#### One to many (inverted)

Add a method to a model class to tell the ORM that a model instance belongs to one related instance:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\HasMany;


class Post extends Model
{
    public $user_id;
    // ...

    public static function author()
    {
        return new BelongsTo(User::class, 'user_id');
    }

    // ...
}
```

The `author` method name is the relation name. You can set any name. The relation above tells that the `Post` model has 
the `user_id` field which contains an author instance identifier.

If you need the `user_id` field to point to another `User` field, pass it's name to the third `BelongsTo` argument:

```php
return new BelongsTo(User::class, 'user_email', 'email');
```

#### Getting models filtered by relations

Get all models having at least one related instance:

```php
$usersWithPosts = $orm
    ->model(User::class)
    ->whereRelation('posts') // The relation name specified in the User model class
    ->get();
```

Get all models related with a model instance:

```php
$user = $orm->model(User::class)->find(12);
$userPosts = $orm
    ->model(Post::class)
    ->whereRelation('author', $user)
    ->get();
```

Get all models having at least one related instance which fits a clause:

```php
$usersWithOldPosts = $orm
    ->model(User::class)
    ->whereRelation('posts', function ($query) {
        $query->where('date', '<', '2015-01-01');
    })
    ->get();
```

You can even filter by a complex relation chain:

```php
// All users having a post which category name is News
$newsWriters = $orm
    ->model(User::class)
    ->whereRelation('posts.category', function ($query) { // Relations are divided by dot
        $query->where('name', 'News');
    })
    ->get();
```

You can also use the `orWhereRelation`, `whereNoRelation` and `orWhereNoRelation` methods.


## Versions compatibility

The project follows the [Semantic Versioning](http://semver.org).


## License

MIT. See [the LICENSE](LICENSE) file for details.
