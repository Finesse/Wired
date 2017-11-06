<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Mapper;
use Finesse\Wired\Tests\ModelsForTests\User;

/**
 * Tests the Mapper class and all the main features.
 *
 * @author Surgie
 */
class MapperTest extends TestCase
{
    /**
     * Tests the `create` method
     */
    public function testCreate()
    {
        $mapper = Mapper::create([
            'driver' => 'sqlite',
            'dsn' => 'sqlite::memory:',
            'prefix' => 'foo'
        ]);
        $this->assertEquals('foo', $mapper->getDatabase()->getTablePrefixer()->tablePrefix);

        $this->assertException(DatabaseException::class, function () {
            Mapper::create(['dsn' => 'foo:bar']);
        });
    }

    /**
     * Tests the `model` method
     */
    public function testModel()
    {
        $mapper = $this->makeMockDatabase();
        $this->assertEquals(26, $mapper->model(User::class)->count());
        $this->assertException(NotModelException::class, function () use ($mapper) {
            $mapper->model('foo');
        });
    }

    /**
     * Tests basic model retrieving
     */
    public function testModelsRetrieving()
    {
        $mapper = $this->makeMockDatabase();

        $users = $mapper
            ->model(User::class)
            ->where('name', '<', 'T')
            ->orderBy('id', 'desc')
            ->offset(9)
            ->limit(6)
            ->get();
        $this->assertCount(6, $users);
        $this->assertAttributes(['id' => 8, 'name' => 'Hannah', 'email' => 'hannah@test.com'], $users[2]);
        $this->assertAttributes(['id' => 5, 'name' => 'Edward', 'email' => 'edward@test.com'], $users[5]);

        $this->assertEquals(10, $mapper
            ->model(User::class)
            ->where('email', '>=', 'quentin@test.com')
            ->count());
    }
}
