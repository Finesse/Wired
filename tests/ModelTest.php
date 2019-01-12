<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Model;
use Finesse\Wired\RelationInterface;
use Finesse\Wired\Relations\BelongsTo;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;

/**
 * Tests the Model class
 *
 * @author Surgie
 */
class ModelTest extends TestCase
{
    /**
     * Tests the `createFromRow` method
     */
    public function testCreateFromRow()
    {
        $model = new class extends Model {
            public $foo = 1;
            public $bar = 2;
            public static function getTable(): string {
                return 'test';
            }
        };

        $model1 = $model::createFromRow(['foo' => 5, 'baz' => 10]);
        $this->assertCount(3, get_object_vars($model1));
        $this->assertAttributes(['foo' => 5, 'bar' => 2, 'baz' => 10], $model1);
    }

    /**
     * Tests the `convertToRow` method
     */
    public function testConvertToRow()
    {
        $model = new class extends Model {
            public $foo;
            public $bar = 5;
            public $baz = 'zzz';
            protected $secret1 = 'qwerty';
            private $secret2 = 12345;
            public static function getTable(): string {
                return 'test';
            }
        };
        $model->baz = 'abc';
        $model->foo_count = 10;

        $this->assertEquals(['foo' => null, 'bar' => 5, 'baz' => 'abc'], $model->convertToRow());
    }

    /**
     * Tests the `doesExistInDatabase` method
     */
    public function testDoesExistInDatabase()
    {
        $user = new User();
        $this->assertFalse($user->doesExistInDatabase());

        $user->id = 5;
        $this->assertTrue($user->doesExistInDatabase());
    }

    /**
     * Tests the `getRelation` and `getRelationOrFail` methods
     */
    public function testGetRelation()
    {
        $model = new class extends Model {
            public static function getTable(): string {
                return 'test';
            }
            public static function parent() {
                return new BelongsTo(User::class, 'bar');
            }
            public static function notARelation() {
                return 'foo bar';
            }
            protected static function notAPublicMethod() {
                return new BelongsTo(User::class, 'bar');
            }
        };

        $this->assertInstanceOf(RelationInterface::class, $model::getRelation('parent'));
        $this->assertInstanceOf(RelationInterface::class, $model::getRelationOrFail('parent'));
        $this->assertNull($model::getRelation('notARelation'));
        $this->assertNull($model::getRelation('notAPublicMethod'));
        $this->assertNull($model::getRelation('notExistingMethod'));

        $this->assertException(RelationException::class, function () use ($model) {
            $model::getRelationOrFail('fubar');
        }, function (RelationException $exception) use ($model) {
            $this->assertEquals(
                'The relation `fubar` is not defined in the '.get_class($model).' model',
                $exception->getMessage()
            );
        });
    }

    /**
     * Tests the relatives methods
     */
    public function testLoadedRelatives()
    {
        $model = new User();
        $this->assertFalse($model->doesHaveLoadedRelatives('post'));
        $this->assertNull($model->getLoadedRelatives('post'));

        $model->unsetLoadedRelatives('post');
        $this->assertFalse($model->doesHaveLoadedRelatives('post'));
        $this->assertNull($model->getLoadedRelatives('post'));

        $model->setLoadedRelatives('post', new Post());
        $this->assertTrue($model->doesHaveLoadedRelatives('post'));
        $this->assertInstanceOf(Post::class, $model->getLoadedRelatives('post'));

        $model->setLoadedRelatives('post', null);
        $this->assertTrue($model->doesHaveLoadedRelatives('post'));
        $this->assertNull($model->getLoadedRelatives('post'));

        $model->setLoadedRelatives('post', new Post());
        $model->unsetLoadedRelatives('post');
        $this->assertFalse($model->doesHaveLoadedRelatives('post'));
        $this->assertNull($model->getLoadedRelatives('post'));
    }

    /**
     * Tests the `__get` and `__isset` magic method
     */
    public function testGet()
    {
        $model = new User();
        $model->name = 'Foobarer';
        $this->assertTrue(isset($model->name));
        $this->assertEquals('Foobarer', $model->name);

        $model->setLoadedRelatives('posts', [new Post()]);
        $this->assertTrue(isset($model->posts));
        $this->assertCount(1, $model->posts);

        $this->assertFalse(isset($model->subscriptions));
        $this->assertException(\Error::class, function () use ($model) {
            $model->subscriptions;
        }, function (\Error $exception) {
            $this->assertEquals('Undefined property: '.User::class.'::$subscriptions', $exception->getMessage());
        });
    }

    /**
     * Tests the `associate` and `dissociate` methods
     */
    public function testAssociateDissociate()
    {
        $user1 = new User();
        $user1->id = 5;
        $user2 = new User();
        $user2->id = 7;
        $post = new Post();

        $post->associate('author', $user1);
        $this->assertEquals(5, $post->author_id);
        $this->assertEquals(5, $post->author->id);

        $post->associate('author', $user2);
        $this->assertEquals(7, $post->author_id);
        $this->assertEquals(7, $post->author->id);

        $post->dissociate('author');
        $this->assertNull($post->author_id);
        $this->assertNull($post->author);

        $post->associate('author', $user1);
        $post->associate('author', null);
        $this->assertNull($post->author_id);
        $this->assertNull($post->author);

        $this->assertException(RelationException::class, function () use ($user1, $post) {
            $user1->associate('posts', $user1);
        }, function (RelationException $exception) {
            $this->assertEquals('Associating is not available for the `posts` relation', $exception->getMessage());
        });

        $this->assertException(RelationException::class, function () use ($user1, $post) {
            $user1->dissociate('posts');
        }, function (RelationException $exception) {
            $this->assertEquals('Dissociating is not available for the `posts` relation', $exception->getMessage());
        });
    }
}
