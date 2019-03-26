<?php

namespace Finesse\Wired\Tests\MapperFeatures;

use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Tests\ModelsForTests\Category;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;

/**
 * Tests the LoadTrait trait
 *
 * @author Surgie
 */
class LoadTraitTest extends TestCase
{
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
}
