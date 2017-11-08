<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Mapper;
use Finesse\Wired\Model;
use Finesse\Wired\Tests\ModelsForTests\Post;
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

    /**
     * Tests models saving
     */
    public function testSave()
    {
        $mapper = $this->makeMockDatabase();
        /** @var User $user */

        // New instance
        $user = new User();
        $user->name = 'Donald';
        $user->email = 'thegreatwall@usa.gov';
        $mapper->save($user);
        $this->assertEquals(27, $user->id);
        $this->assertEquals(27, $mapper->model(User::class)->count());
        $this->assertAttributes(
            ['id' => 27, 'name' => 'Donald', 'email' => 'thegreatwall@usa.gov'],
            $mapper->model(User::class)->find(27)
        );

        // Existing instance
        $user = $mapper->model(User::class)->find(16);
        $user->name = 'Peter';
        $user->email = 'peter@example.com';
        $mapper->save($user);
        $this->assertEquals(16, $user->id);
        $this->assertEquals(27, $mapper->model(User::class)->count());
        $this->assertAttributes(
            ['id' => 16, 'name' => 'Peter', 'email' => 'peter@example.com'],
            $mapper->model(User::class)->find(16)
        );

        // Many instances at once
        $users = $mapper->model(User::class)->find([4, 13]);
        $users[0]->name = 'Dru';
        $users[1]->email = 'madonna@example.com';
        $newUser = new User();
        $newUser->name = 'Michael';
        $newUser->email = 'bigbuster@mail.test';
        $users[] = $newUser;
        $mapper->save($users);
        $this->assertEquals(28, $newUser->id);
        $this->assertEquals(28, $mapper->model(User::class)->count());
        $users = $mapper->model(User::class)->find([4, 13, 28]);
        $this->assertCount(3, $users);
        $this->assertAttributes(['id' => 4, 'name' => 'Dru', 'email' => 'dick@test.com'], $users[0]);
        $this->assertAttributes(['id' => 13, 'name' => 'Madonna', 'email' => 'madonna@example.com'], $users[1]);
        $this->assertAttributes(['id' => 28, 'name' => 'Michael', 'email' => 'bigbuster@mail.test'], $users[2]);

        // Mixed model classes
        $user = new User();
        $user->name = 'Leo';
        $post = new Post();
        $post->text = 'Lorem ipsum';
        $mapper->save([$user, $post]);
        $this->assertEquals(29, $user->id);
        $this->assertEquals(18, $post->id);

        // Database error
        $model = new class extends Model {
            public $id;
            public static function getTable(): string {
                return 'wrong_table';
            }
        };
        $this->assertException(DatabaseException::class, function () use ($mapper, $model) {
            $mapper->save($model);
        });

        // Incorrect model error
        $user = new User();
        $user->name = ['foo', 'bar'];
        $this->assertException(IncorrectModelException::class, function () use ($mapper, $user) {
            $mapper->save($user);
        });
    }

    /**
     * Tests the `delete` method
     */
    public function testDelete()
    {
        $mapper = $this->makeMockDatabase();
        /** @var User $user */

        // A single model
        $user = $mapper->model(User::class)->find(21);
        $mapper->delete($user);
        $this->assertEquals(25, $mapper->model(User::class)->count());
        $this->assertNull($mapper->model(User::class)->find(21));
        $this->assertNull($user->id);

        // Many models
        $users = $mapper->model(User::class)->find([3, 14, 25]);
        $mapper->delete($users);
        $this->assertEquals(22, $mapper->model(User::class)->count());
        $this->assertEmpty($mapper->model(User::class)->find([3, 14, 25]));
        foreach ($users as $user) {
            $this->assertNull($user->id);
        }

        // Already deleted model
        $user = new User();
        $user->name = 'Colin';
        $mapper->delete($user);
        $this->assertNull($user->id);

        // Mixed model classes
        $user = $mapper->model(User::class)->find(16);
        $post = $mapper->model(Post::class)->find(5);
        $mapper->delete([$user, $post]);
        $this->assertEquals(21, $mapper->model(User::class)->count());
        $this->assertEquals(16, $mapper->model(Post::class)->count());
        $this->assertNull($mapper->model(User::class)->find(16));
        $this->assertNull($mapper->model(Post::class)->find(5));

        // Not a model error
        $this->assertException(NotModelException::class, function () use ($mapper) {
            $mapper->delete('user');
        });

        // Database error
        $model = new class extends Model {
            public $id = 1;
            public static function getTable(): string {
                return 'wrong_table';
            }
        };
        $this->assertException(DatabaseException::class, function () use ($mapper, $model) {
            $mapper->delete($model);
        });

        // Incorrect model error
        $user = new User();
        $user->id = ['foo', 'bar'];
        $this->assertException(IncorrectModelException::class, function () use ($mapper, $user) {
            $mapper->delete($user);
        });
    }
}
