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
}
