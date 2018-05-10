* **Getting started**
* [Relations](relations.md)

# Getting started

## Installation

You need [Composer](https://getcomposer.org) to use this library. Run in a console:
                                                                  
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
[there](https://github.com/Finesse/MiniDB#getting-started).

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

All the table fields must be specified as the public properties of the class. All the public properties must be a table
column names.


## Basic usage

### Retrieving models

Get a model by identifier:

```php
$user = $orm->model(User::class)->find(15); // A User instance or null
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
[there](https://github.com/Finesse/QueryScribe/blob/master/docs/building-queries.md).

#### Pagination

We suggest [Pagerfanta](https://github.com/whiteoctober/Pagerfanta) to easily make pagination.

First install Pagerfanta using [composer](https://getcomposer.org) by running in a console:

```bash
composer require pagerfanta/pagerfanta
```

Then make a query from which the models should be taken:

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

$currentPageModels = $paginator->getCurrentPageResults(); // The models for the current page
$pagesCount = $paginator->getNbPages();                   // Total pages count
$haveToPaginate = $paginator->haveToPaginate();           // Whether the number of models is higher than the max per page
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
            // Process a model here
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

Warning! The `save` method doesn't save related models, you need to do it manually.

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

Warning! The `delete` method doesn't delete related models, you need to do it manually.

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
