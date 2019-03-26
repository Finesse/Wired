<?php

namespace Finesse\Wired\Tests\MapperFeatures;

use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Mapper;
use Finesse\Wired\Relations\BelongsToMany;
use Finesse\Wired\Tests\ModelsForTests\Category;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;

/**
 * Tests the AttachTrait trait
 *
 * @author Surgie
 */
class AttachTraitTest extends TestCase
{
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
        $post = new Post();
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
