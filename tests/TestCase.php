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
            $this->assertInstanceOf($expectClass, $exception, "The thrown exception is not instance of $expectClass");
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

        // Just to be sure that the table prefix is applied
        $this->assertNotEquals('users', $database->addTablePrefix('users'));

        $database->statement('
            CREATE TABLE '.$database->addTablePrefix('users').' (
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

        $database->statement('
            CREATE TABLE '.$database->addTablePrefix('categories').' (
                id INTEGER PRIMARY KEY ASC,
                parent_id INTEGER, 
                title TEXT
            )
        ');
        $database->table('categories')->insert([
            ['id' => 1, 'parent_id' => null, 'title' => 'News'],
            ['id' => 2, 'parent_id' => null, 'title' => 'Articles'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'Economics'],
            ['id' => 4, 'parent_id' => 1, 'title' => 'Sport'],
            ['id' => 5, 'parent_id' => 4, 'title' => 'Hockey'],
            ['id' => 6, 'parent_id' => 4, 'title' => 'Football'],
            ['id' => 7, 'parent_id' => 2, 'title' => 'Lifehacks'],
            ['id' => 8, 'parent_id' => 2, 'title' => 'Receipts'],

            // Recursion
            ['id' => 9, 'parent_id' => 10, 'title' => 'Tick'],
            ['id' => 10, 'parent_id' => 9, 'title' => 'Tack'],
            ['id' => 11, 'parent_id' => 11, 'title' => 'Selfparent']
        ]);

        $database->statement('
            CREATE TABLE '.$database->addTablePrefix('posts').' (
                key INTEGER PRIMARY KEY ASC,
                author_id INTEGER, 
                category_id INTEGER, 
                text TEXT,
                created_at INTEGER
            )
        ');
        $database->table('posts')->insert([
            ['key' => 1, 'author_id' => 6, 'created_at' => mktime(0, 0, 0, 11, 1, 2017), 'category_id' => 1, 'text' => 'Well, Prince, so Genoa and Lucca are now just family estates of the Buonapartes'],
            ['key' => 2, 'author_id' => 12, 'created_at' => mktime(14, 0, 0, 11, 1, 2017), 'category_id' => 2, 'text' => 'But I warn you, if you don’t tell me that this means war, if you still try to defend the infamies and horrors perpetrated by that Antichrist'],
            ['key' => 3, 'author_id' => 17, 'created_at' => mktime(0, 0, 0, 11, 2, 2017), 'category_id' => 3, 'text' => 'I really believe he is Antichrist'],
            ['key' => 4, 'author_id' => 26, 'created_at' => mktime(12, 0, 0, 11, 2, 2017), 'category_id' => 4, 'text' => 'I will have nothing more to do with you and you are no longer my friend, no longer my ‘faithful slave,’ as you call yourself!'],
            ['key' => 5, 'author_id' => 6, 'created_at' => mktime(0, 0, 0, 11, 3, 2017), 'category_id' => 5, 'text' => 'But how do you do?'],
            ['key' => 6, 'author_id' => 11, 'created_at' => mktime(13, 0, 0, 11, 3, 2017), 'category_id' => 6, 'text' => ' I see I have frightened you—sit down and tell me all the news'],
            ['key' => 7, 'author_id' => 1, 'created_at' => mktime(0, 0, 0, 11, 4, 2017), 'category_id' => 7, 'text' => 'Well, Prince, so Genoa and Lucca are now just family estates of the Buonapartes'],
            ['key' => 8, 'author_id' => 17, 'created_at' => mktime(16, 0, 0, 11, 4, 2017), 'category_id' => 8, 'text' => 'It was in July, 1805, and the speaker was the well-known Anna Pavlovna Scherer, maid of honor and favorite of the Empress Marya Fedorovna'],
            ['key' => 9, 'author_id' => 6, 'created_at' => mktime(0, 0, 0, 11, 5, 2017), 'category_id' => 5, 'text' => 'Well, Prince, so Genoa and Lucca are now just family estates of the Buonapartes'],
            ['key' => 10, 'author_id' => 24, 'created_at' => mktime(9, 0, 0, 11, 5, 2017), 'category_id' => null, 'text' => 'With these words she greeted Prince Vasili Kuragin, a man of high rank and importance, who was the first to arrive at her reception'],
            ['key' => 11, 'author_id' => -10, 'created_at' => mktime(0, 0, 0, 11, 6, 2017), 'category_id' => 2, 'text' => 'Anna Pavlovna had had a cough for some days'],
            ['key' => 12, 'author_id' => 11, 'created_at' => mktime(2, 0, 0, 11, 6, 2017), 'category_id' => 7, 'text' => 'She was, as she said, suffering from la grippe; grippe being then a new word in St. Petersburg, used only by the elite'],
            ['key' => 13, 'author_id' => 8, 'created_at' => mktime(0, 0, 0, 11, 7, 2017), 'category_id' => 4, 'text' => 'All her invitations without exception, written in French, and delivered by a scarlet-liveried footman that morning, ran as follows'],
            ['key' => 14, 'author_id' => 3, 'created_at' => mktime(19, 0, 0, 11, 7, 2017), 'category_id' => 6, 'text' => 'If you have nothing better to do, Count (or Prince), and if the prospect of spending an evening with a poor invalid is not too terrible, I shall be very charmed to see you tonight between 7 and 10—Annette Scherer'],
            ['key' => 15, 'author_id' => null, 'created_at' => mktime(0, 0, 0, 11, 8, 2017), 'category_id' => 3, 'text' => 'Heavens! what a virulent attack!'],
            ['key' => 16, 'author_id' => 10, 'created_at' => mktime(5, 0, 0, 11, 8, 2017), 'category_id' => 8, 'text' => 'Replied the prince, not in the least disconcerted by this reception'],
            ['key' => 17, 'author_id' => 6, 'created_at' => mktime(11, 0, 0, 11, 8, 2017), 'category_id' => null, 'text' => 'He had just entered, wearing an embroidered court uniform, knee breeches, and shoes, and had stars on his breast and a serene expression on his flat face'],
        ]);

        return new Mapper($database);
    }
}
