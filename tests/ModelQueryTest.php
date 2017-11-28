<?php

namespace Finesse\Wired\Tests;

use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\MiniDB\Query;
use Finesse\QueryScribe\ClosureResolverInterface;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\QueryScribe\Query as OriginalQuery;

/**
 * Tests the ModelQuery class
 *
 * @author Surgie
 */
class ModelQueryTest extends TestCase
{
    /**
     * Tests the `getModelClass` method
     */
    public function testGetModelClass()
    {
        $query = new class extends Query {
            public function __construct() {}
        };

        $modelQuery = new ModelQuery($query);
        $this->assertNull($modelQuery->getModelClass());

        $modelQuery = new ModelQuery($query, User::class);
        $this->assertEquals(User::class, $modelQuery->getModelClass());
    }

    /**
     * Tests the `getBaseQuery` method
     */
    public function testGetBaseQuery()
    {
        $query = new class extends Query {
            public function __construct() {}
        };
        $modelQuery = new ModelQuery($query);

        $this->assertEquals($query, $modelQuery->getBaseQuery());
    }

    /**
     * Tests the errors handling
     */
    public function testErrors()
    {
        $query = new class extends Query {
            public function __construct() {}
            public function get(): array {
                throw new DBDatabaseException();
            }
            public function first() {
                throw new DBIncorrectQueryException();
            }
            public function avg($column) {
                throw new DBInvalidArgumentException();
            }
            public function delete(): int {
                throw new \RuntimeException();
            }
            public function makeCopyForCriteriaGroup(): OriginalQuery {
                throw new \InvalidArgumentException();
            }
        };
        $modelQuery = new ModelQuery($query);

        $this->assertException(InvalidArgumentException::class, function () use ($modelQuery) {
            $modelQuery->avg('foo');
        });
        $this->assertException(IncorrectQueryException::class, function () use ($modelQuery) {
            $modelQuery->first();
        });
        $this->assertException(DatabaseException::class, function () use ($modelQuery) {
            $modelQuery->get();
        });
        $this->assertException(InvalidReturnValueException::class, function () use ($modelQuery) {
            $modelQuery->whereExists(function () {
                return 'boo';
            });
        });
        $this->assertException(\RuntimeException::class, function () use ($modelQuery) {
            $modelQuery->delete();
        });
        $this->assertException(\InvalidArgumentException::class, function () use ($modelQuery) {
            $modelQuery->where(function () {});
        });

        // Error in the constructor
        $query = new class extends Query {
            public function __construct() {}
            public function setClosureResolver(ClosureResolverInterface $closureResolver = null) {
                throw new DBIncorrectQueryException();
            }
        };
        $this->assertException(IncorrectQueryException::class, function () use ($query) {
            new ModelQuery($query);
        });
    }

    /**
     * Tests that database rows are turned to model instances
     */
    public function testRowsProcessing()
    {
        $mapper = $this->makeMockDatabase();

        // Query has a model class
        $users = $mapper->model(User::class)->orderBy('id')->limit(5)->get();
        $this->assertCount(5, $users);
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
        }
        $this->assertAttributes(['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'], $users[1]);
        $this->assertAttributes(['id' => 5, 'name' => 'Edward', 'email' => 'edward@test.com'], $users[4]);

        // Query doesn't have a model class
        $query = $mapper->getDatabase()->table(User::getTable());
        $modelQuery = new ModelQuery($query);
        $users = $modelQuery->orderBy('id')->limit(5)->get();
        $this->assertCount(5, $users);
        foreach ($users as $user) {
            $this->assertInternalType('array', $user);
        }
        $this->assertEquals(['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'], $users[1]);
        $this->assertEquals(['id' => 5, 'name' => 'Edward', 'email' => 'edward@test.com'], $users[4]);

        // Single model
        $user = $mapper->model(User::class)->where('name', '>', 'B')->orderBy('id')->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertAttributes(['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'], $user);
    }

    /**
     * Tests that the query closures are resolved right
     */
    public function testClosureResolving()
    {
        $mapper = $this->makeMockDatabase();

        $query = $mapper
            ->model(User::class)
            ->where(function (ModelQuery $query) {
                $this->assertEquals(User::class, $query->getModelClass());
                $query->where('name', 'Jackie')->orWhere('email', 'like', 'jackie');
            })
            ->whereExists(function (ModelQuery $query) {
                $this->assertNull($query->getModelClass());
                $query->from('foo')->where('bar', 1);
            })
            ->getBaseQuery();

        $this->assertCount(2, $query->where);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertEquals(User::getTable(), $query->table);
    }

    /**
     * Tests the `makeModelSubQuery` method
     */
    public function testMakeModelSubQuery()
    {
        $mapper = $this->makeMockDatabase();

        // With different models
        $query = $mapper->model(User::class);
        $subQuery = $query->makeModelSubQuery(Post::class);
        $this->assertInstanceOf(ModelQuery::class, $subQuery);
        $this->assertAttributeEquals(Post::getTable(), 'table', $subQuery->getBaseQuery());
        $this->assertNotEquals($query->getTableIdentifier(), $subQuery->getTableIdentifier());

        // With same model
        $subQuery = $query->makeModelSubQuery(User::class);
        $this->assertInstanceOf(ModelQuery::class, $subQuery);
        $this->assertAttributeEquals(User::getTable(), 'table', $subQuery->getBaseQuery());
        $this->assertNotEquals($query->getTableIdentifier(), $subQuery->getTableIdentifier());

        // Wrong model name
        $this->assertException(NotModelException::class, function () use ($query) {
            $query->makeModelSubQuery(self::class);
        });
    }

    /**
     * Tests the `find` method
     */
    public function testFind()
    {
        $mapper = $this->makeMockDatabase();

        // One existing model
        $user = $mapper->model(User::class)->find(22);
        $this->assertInstanceOf(User::class, $user);
        $this->assertAttributes(['id' => 22, 'name' => 'Vladimir', 'email' => 'vladimir@test.com'], $user);

        // One not existing model
        $this->assertNull($mapper->model(User::class)->find(49));

        // Many mixed models
        $users = $mapper->model(User::class)->find([5, 17, 41]);
        $this->assertInternalType('array', $users);
        $this->assertCount(2, $users);
        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertAttributes(['id' => 5, 'name' => 'Edward', 'email' => 'edward@test.com'], $users[0]);
        $this->assertInstanceOf(User::class, $users[1]);
        $this->assertAttributes(['id' => 17, 'name' => 'Quentin', 'email' => 'quentin@test.com'], $users[1]);

        // No associated model
        $query = $mapper->getDatabase()->table(User::getTable());
        $modelQuery = new ModelQuery($query);
        $this->assertException(IncorrectQueryException::class, function () use ($modelQuery) {
            $modelQuery->find(10);
        });
    }

    /**
     * Tests the `chunk` method
     */
    public function testChunk()
    {
        $mapper = $this->makeMockDatabase();

        $mapper->model(User::class)->orderBy('id')->chunk(10, function ($users) {
            foreach ($users as $user) {
                $this->assertInstanceOf(User::class, $user);
            }
        });
    }

    /**
     * Tests the common `whereRelation` method features
     */
    public function testWhereRelation()
    {
        $mapper = $this->makeMockDatabase();

        // Companion methods
        $this->assertEquals(2, $mapper
            ->model(Post::class)
            ->whereNoRelation('author')
            ->count());

        $this->assertEquals(16, $mapper
            ->model(Post::class)
            ->where('created_at', '>=', mktime(0, 0, 0, 11, 7, 2017))
            ->orWhereRelation('author')
            ->count());

        $this->assertEquals(5, $mapper
            ->model(Post::class)
            ->where('created_at', '<=', mktime(0, 0, 0, 11, 2, 2017))
            ->orWhereNoRelation('author')
            ->count());

        // Nested relations criteria
        $users = $mapper
            ->model(User::class)
            ->whereRelation('posts', function (ModelQuery $query) {
                $query->whereRelation('category', function (ModelQuery $query) {
                    $query->where('title', 'Hockey')->orWhere('id', 3);
                });
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $users);
        $this->assertEquals('Frank', $users[0]->name);
        $this->assertEquals('Quentin', $users[1]->name);

        // Relations chain criterion
        $users = $mapper
            ->model(User::class)
            ->whereRelation('posts.category', function (ModelQuery $query) {
                $query->where('title', 'Hockey')->orWhere('id', 3);
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $users);
        $this->assertEquals('Frank', $users[0]->name);
        $this->assertEquals('Quentin', $users[1]->name);

        // Not a model query
        $query = $mapper->getDatabase()->table(User::getTable());
        $modelQuery = new ModelQuery($query);
        $this->assertException(IncorrectQueryException::class, function () use ($modelQuery) {
            $modelQuery->whereRelation('foo');
        }, function (IncorrectQueryException $exception) {
            $this->assertEquals('This query is not a model query', $exception->getMessage());
        });

        // Not defined relation
        $this->assertException(RelationException::class, function () use ($mapper) {
            $mapper->model(User::class)->whereRelation('undefined_relation');
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('The relation `undefined_relation` is not defined', $exception->getMessage());
        });
    }
}
