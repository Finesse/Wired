<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Mapper;
use Finesse\Wired\Model;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Relations\BelongsToMany;
use Finesse\Wired\Tests\ModelsForTests\Category;
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
        $this->assertEquals(18, $post->key);

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

        // No models
        $mapper->delete([]);
        $this->assertEquals(21, $mapper->model(User::class)->count());
        $this->assertEquals(16, $mapper->model(Post::class)->count());

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

    /**
     * Tests the `load` method
     */
    public function testLoad()
    {
        $mapper = $this->makeMockDatabase();

        // Single model
        /** @var User $user1 */
        $user1 = $mapper->model(User::class)->find(11);
        $mapper->load($user1, 'posts');
        $this->assertCount(2, $user1->posts);
        $this->assertEquals(6, $user1->posts[0]->key);
        $this->assertEquals(12, $user1->posts[1]->key);

        // Load only missing
        /** @var User $user2 */
        $post = $user1->posts[0];
        $post->mark = 'test';
        $user2 = $mapper->model(User::class)->find(6);
        $mapper->load([$user1, $user2], 'posts', function (ModelQuery $query) {
            $query->where('created_at', '<', mktime(0, 0, 0, 11, 6, 2017));
        }, true);
        $this->assertCount(2, $user1->posts);
        $this->assertEquals('test', $user1->posts[0]->mark); // The first user post is not overridden
        $this->assertCount(3, $user2->posts);

        // Load many models
        $posts = $mapper->model(Post::class)->get();
        $mapper->load($posts, 'author');
        $mapper->load($posts, 'category');
        foreach ($posts as $post) {
            $this->assertTrue(isset($post->author));
            $this->assertTrue(isset($post->category));
        }

        // Chained relation
        /** @var User $user */
        $user = $mapper->model(User::class)->find(11);
        $mapper->load($user, 'posts.category');
        $this->assertCount(2, $user->posts);
        $this->assertEquals('Football', $user->posts[0]->category->title);
        $this->assertEquals('Lifehacks', $user->posts[1]->category->title);

        // Chained relation without relatives on the first level
        $user = $mapper->model(User::class)->find(19);
        $mapper->load($user, 'posts.category');
        $this->assertCount(0, $user->posts);

        // Undefined relation
        $this->assertException(RelationException::class, function () use ($mapper, $posts) {
            $mapper->load($posts, 'fubar');
        }, function (RelationException $exception) {
            $this->assertEquals(
                'The relation `fubar` is not defined in the '.Post::class.' model',
                $exception->getMessage()
            );
        });
    }

    /**
     * Tests the `loadCyclic` method
     */
    public function testLoadCyclic()
    {
        $mapper = $this->makeMockDatabase();

        // Many related
        /** @var Category $category */
        $category = $mapper->model(Category::class)->find(1);
        $mapper->loadCyclic($category, 'children');
        $this->assertCount(2, $category->children);
        $this->assertEquals('Economics', $category->children[0]->title);
        $this->assertEquals('Sport', $category->children[1]->title);
        $this->assertCount(0, $category->children[0]->children);
        $this->assertCount(2, $category->children[1]->children);
        $this->assertEquals('Hockey', $category->children[1]->children[0]->title);
        $this->assertCount(0, $category->children[1]->children[0]->children);
        $this->assertEquals('Football', $category->children[1]->children[1]->title);
        $this->assertCount(0, $category->children[1]->children[1]->children);

        // One related
        $category = $mapper->model(Category::class)->find(4);
        $mapper->loadCyclic($category, 'parent');
        $this->assertEquals('News', $category->parent->title);
        $this->assertNull($category->parent->parent);

        // Recursive relation (many)
        $category = $mapper->model(Category::class)->find(9);
        $mapper->loadCyclic($category, 'children');
        $this->assertCount(1, $category->children);
        $this->assertEquals('Tack', $category->children[0]->title);
        $this->assertCount(1, $category->children[0]->children);
        $this->assertEquals($category, $category->children[0]->children[0]);

        // Recursive relation (one)
        $category = $mapper->model(Category::class)->find(9);
        $mapper->loadCyclic($category, 'parent');
        $this->assertEquals('Tack', $category->parent->title);
        $this->assertEquals($category, $category->parent->parent);

        // Recursive relation (selfparent, many)
        $category = $mapper->model(Category::class)->find(11);
        $mapper->loadCyclic($category, 'children');
        $this->assertCount(1, $category->children);
        $this->assertEquals($category, $category->children[0]);

        // Recursive relation (selfparent, one)
        $category = $mapper->model(Category::class)->find(11);
        $mapper->loadCyclic($category, 'parent');
        $this->assertEquals($category, $category->parent);

        // Chained cyclic relation
        /** @var Post $post */
        $post = $mapper->model(Post::class)->find(6);
        $mapper->loadCyclic($post, 'author.posts');
        $this->assertEquals('Kenny', $post->author->name);
        $this->assertCount(2, $post->author->posts);
        $this->assertEquals($post, $post->author->posts[0]);
        $this->assertEquals(12, $post->author->posts[1]->key);
        $this->assertEquals($post->author, $post->author->posts[1]->author);

        // Not a cyclic relation
        $category = $mapper->model(Category::class)->find(4);
        $this->assertException(RelationException::class, function () use ($mapper, $category) {
            $mapper->loadCyclic($category, 'posts');
        }, function (RelationException $exception) {
            $this->assertEquals(
                'The relation `posts` is not defined in the '.Post::class.' model;'
                    . ' perhaps, the given relation is not cycled',
                $exception->getMessage()
            );
        });

        // Not existing relation
        $category = $mapper->model(Category::class)->find(4);
        $this->assertException(RelationException::class, function () use ($mapper, $category) {
            $mapper->loadCyclic($category, 'author');
        }, function (RelationException $exception) {
            $this->assertEquals(
                'The relation `author` is not defined in the '.Category::class.' model',
                $exception->getMessage()
            );
        });
    }

    /**
     * Tests the `attach` method
     */
    public function testAttach()
    {
        $mapper = $this->makeMockDatabase();

        // The database logic is pretty extensive and it's already tested comprehensively therefore it's skipped here

        // One model on each side
        $user = new class extends User {
            public static $relationMock;
            public static function testRelation() {
                return static::$relationMock;
            }
        };
        $post = new Post;
        $getAttachmentData = function() {};
        $user::$relationMock = $this->createMock(BelongsToMany::class);
        $user::$relationMock
            ->expects($this->once())
            ->method('attach')
            ->with($mapper, [$user], [$post], Mapper::DUPLICATE, false, $getAttachmentData);
        $mapper->attach($user, 'testRelation', $post, $getAttachmentData);

        // Many models
        $category = new class extends Category {
            public static $relationMock;
            public static function testRelation() {
                return static::$relationMock;
            }
        };
        $posts = [new Post, new Post, new Post];
        $user::$relationMock = $this->createMock(BelongsToMany::class);
        $user::$relationMock
            ->expects($this->exactly(2))
            ->method('attach')
            ->withConsecutive(
                [$mapper, [$user], $posts, Mapper::DUPLICATE, false, null],
                [$mapper, [$user], [$user], Mapper::DUPLICATE, false, null]
            );
        $category::$relationMock = $this->createMock(BelongsToMany::class);
        $category::$relationMock
            ->expects($this->exactly(2))
            ->method('attach')
            ->withConsecutive(
                [$mapper, [$category], $posts, Mapper::DUPLICATE, false, null],
                [$mapper, [$category], [$user], Mapper::DUPLICATE, false, null]
            );
        $mapper->attach([$user, $category], 'testRelation', array_merge($posts, [$user]));

        // Synchronize
        $user::$relationMock = $this->createMock(BelongsToMany::class);
        $user::$relationMock
            ->expects($this->exactly(3))
            ->method('attach')
            ->withConsecutive(
                [$mapper, [$user], [$post], Mapper::UPDATE, true, null],
                [$mapper, [$user], [$post], Mapper::UPDATE, false, $getAttachmentData],
                [$mapper, [$user], [$post], Mapper::REPLACE, true, null]
            );
        $mapper->setAttachments($user, 'testRelation', $post);
        $mapper->setAttachments($user, 'testRelation', $post, true, $getAttachmentData);
        $mapper->setAttachments($user, 'testRelation', $post, false, null, true);

        // Zero child models (equivalent to detach all)
        $user::$relationMock = $this->createMock(BelongsToMany::class);
        $user::$relationMock
            ->expects($this->once())
            ->method('attach')
            ->with($mapper, [$user], [], Mapper::UPDATE, true, null);
        $mapper->setAttachments($user, 'testRelation', []);

        // Unsupported relation
        $this->assertException(RelationException::class, function () use ($mapper, $user, $post) {
            $mapper->attach($user, 'posts', $post);
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('Attaching is not available', $exception->getMessage());
        });

        // Undefined relation
        $this->assertException(RelationException::class, function () use ($mapper, $user, $post) {
            $mapper->attach($user, 'foobar', $post);
        }, function (RelationException $exception) {
            $this->assertContains('is not defined', $exception->getMessage());
        });
    }

    /**
     * Tests the `detach` method
     */
    public function testDetach()
    {
        $mapper = $this->makeMockDatabase();
        $categories = $mapper->model(Category::class)->orderBy('id')->find([5, 6, 7]);
        $users = $mapper->model(User::class)->orderBy('id')->find([1, 6, 11]);
        $attachmentsCount = $mapper->model(Post::class)->count();

        // One attachment
        $mapper->detach($categories[1], 'authors', $users[2]);
        $this->assertEquals($attachmentsCount - 1, $mapper->model(Post::class)->count());

        // Many attachments
        $mapper->detach($categories, 'authors', $users);
        $this->assertEquals($attachmentsCount - 5, $mapper->model(Post::class)->count());

        // No attachments
        $mapper->detach([], 'authors', []);
        $this->assertEquals($attachmentsCount - 5, $mapper->model(Post::class)->count());

        // Unsupported relation
        $this->assertException(RelationException::class, function () use ($mapper, $categories, $users) {
            $mapper->detach($categories, 'posts', $users);
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('Detaching is not available', $exception->getMessage());
        });

        // Undefined relation
        $this->assertException(RelationException::class, function () use ($mapper, $categories, $users) {
            $mapper->detach($categories, 'foobar', $users);
        }, function (RelationException $exception) {
            $this->assertContains('is not defined', $exception->getMessage());
        });
    }

    /**
     * Tests the `detachAll` method
     */
    public function testDetachAll()
    {
        $mapper = $this->makeMockDatabase();
        $categories = $mapper->model(Category::class)->orderBy('id')->find([5, 6, 7]);
        $attachmentsCount = $mapper->model(Post::class)->count();

        // One model
        $mapper->detachAll($categories[1], 'authors');
        $this->assertEquals($attachmentsCount - 2, $mapper->model(Post::class)->count());

        // Many models
        $mapper->detachAll($categories, 'authors');
        $this->assertEquals($attachmentsCount - 6, $mapper->model(Post::class)->count());

        // No models
        $mapper->detachAll([], 'authors');
        $this->assertEquals($attachmentsCount - 6, $mapper->model(Post::class)->count());

        // Unsupported relation
        $this->assertException(RelationException::class, function () use ($mapper, $categories) {
            $mapper->detachAll($categories, 'posts');
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('Detaching is not available', $exception->getMessage());
        });

        // Undefined relation
        $this->assertException(RelationException::class, function () use ($mapper, $categories) {
            $mapper->detachAll($categories, 'foobar');
        }, function (RelationException $exception) {
            $this->assertContains('is not defined', $exception->getMessage());
        });
    }
}
