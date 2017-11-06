<?php

namespace Finesse\Wired\Tests;

use Finesse\MiniDB\Database;
use Finesse\Wired\Mapper;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for the tests.
 *
 * @author Surgie
 */
class TestCase extends BaseTestCase
{
    /**
     * Asserts that the given callback throws the given exception.
     *
     * @param string $expectClass The name of the expected exception class
     * @param callable $callback A callback which should throw the exception
     * @param callable|null $onException A function to call after exception check. It may be used to test the exception.
     */
    protected function assertException(string $expectClass, callable $callback, callable $onException = null)
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $this->assertInstanceOf($expectClass, $exception);
            if ($onException) {
                $onException($exception);
            }
            return;
        }

        $this->fail('No exception has been thrown');
    }

    /**
     * Asserts that the given object has the given attributes with the given values.
     *
     * @param array $expectedAttributes Attributes. The indexes are the attributes names, the values are the attributes
     *    values.
     * @param mixed $actualObject Object
     */
    protected function assertAttributes(array $expectedAttributes, $actualObject)
    {
        foreach ($expectedAttributes as $property => $value) {
            $this->assertObjectHasAttribute($property, $actualObject);
            $this->assertAttributeEquals($value, $property, $actualObject);
        }
    }

    /**
     * Creates a mock database filled with models
     *
     * @return Mapper Mapper connected with the database
     */
    protected function makeMockDatabase(): Mapper
    {
        $database = Database::create([
            'driver' => 'sqlite',
            'dsn' => 'sqlite::memory:',
            'prefix' => 'test_'
        ]);

        $this->assertNotEquals('users', $database->addTablePrefix('users'));
        $database->statement('
            CREATE TABLE '.$database->addTablePrefix('users').'(
                id INTEGER PRIMARY KEY ASC, 
                name TEXT, 
                email TEXT
            )
        ');
        $database->table('users')->insert([
            ['id' => 1, 'name' => 'Anny', 'email' => 'anny@test.com'],
            ['id' => 2, 'name' => 'Bob', 'email' => 'bob@test.com'],
            ['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@test.com'],
            ['id' => 4, 'name' => 'Dick', 'email' => 'dick@test.com'],
            ['id' => 5, 'name' => 'Edward', 'email' => 'edward@test.com'],
            ['id' => 6, 'name' => 'Frank', 'email' => 'frank@test.com'],
            ['id' => 7, 'name' => 'Ginny', 'email' => 'ginny@test.com'],
            ['id' => 8, 'name' => 'Hannah', 'email' => 'hannah@test.com'],
            ['id' => 9, 'name' => 'Iggy', 'email' => 'iggy@test.com'],
            ['id' => 10, 'name' => 'Jack', 'email' => 'jack@test.com'],
            ['id' => 11, 'name' => 'Kenny', 'email' => 'kenny@test.com'],
            ['id' => 12, 'name' => 'Linda', 'email' => 'linda@test.com'],
            ['id' => 13, 'name' => 'Madonna', 'email' => 'madonna@test.com'],
            ['id' => 14, 'name' => 'Nicole', 'email' => 'nicole@test.com'],
            ['id' => 15, 'name' => 'Oliver', 'email' => 'oliver@test.com'],
            ['id' => 16, 'name' => 'Pam', 'email' => 'pam@test.com'],
            ['id' => 17, 'name' => 'Quentin', 'email' => 'quentin@test.com'],
            ['id' => 18, 'name' => 'Rick', 'email' => 'rick@test.com'],
            ['id' => 19, 'name' => 'Susan', 'email' => 'susan@test.com'],
            ['id' => 20, 'name' => 'Tracy', 'email' => 'tracy@test.com'],
            ['id' => 21, 'name' => 'Uma', 'email' => 'uma@test.com'],
            ['id' => 22, 'name' => 'Vladimir', 'email' => 'vladimir@test.com'],
            ['id' => 23, 'name' => 'William', 'email' => 'william@test.com'],
            ['id' => 24, 'name' => 'Xenia', 'email' => 'xenia@test.com'],
            ['id' => 25, 'name' => 'Yury', 'email' => 'yury@test.com'],
            ['id' => 26, 'name' => 'Zach', 'email' => 'zach@test.com']
        ]);

        return new Mapper($database);
    }
}
