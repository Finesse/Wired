<?php

namespace Finesse\Wired\Tests;

use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\MiniDB\Query;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Tests\ModelsForTests\User;

/**
 * Tests the ModelQuery class
 *
 * @author Surgie
 */
class ModelQueryTest extends TestCase
{
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
        $this->assertException(\RuntimeException::class, function () use ($modelQuery) {
            $modelQuery->delete();
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
        $query = $mapper->getDatabase()->table(User::getTable());
        $modelQuery = new class ($query, User::class) extends ModelQuery {
            public function getModelClass() {
                return $this->modelClass;
            }
        };

        $modelQuery
            ->where(function ($query) {
                $this->assertInstanceOf(ModelQuery::class, $query);
                $this->assertEquals(User::class, $query->getModelClass());
                $query->where('name', 'Jackie')->orWhere('email', 'like', 'jackie');
            })
            ->whereExists(function ($query) {
                $this->assertInstanceOf(ModelQuery::class, $query);
                $this->assertNull($query->getModelClass());
                $query->from('foo')->where('bar', 1);
            });

        $query = $modelQuery->getBaseQuery();
        $this->assertCount(2, $query->where);
        $this->assertCount(2, $query->where[0]->criteria);
        $this->assertEquals(User::getTable(), $query->table);
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
    }
}
