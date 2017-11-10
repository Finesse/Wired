<?php

namespace Finesse\Wired\Tests\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Tests\ModelsForTests\Category;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;

/**
 * Tests the BelongsTo relation class
 *
 * @author Surgie
 */
class BelongsToTest extends TestCase
{
    /**
     * Tests the `applyToQueryWhere` method
     */
    public function testApplyToQueryWhere()
    {
        $mapper = $this->makeMockDatabase();

        // Relation with no constraints
        $this->assertEquals(15, $mapper
            ->model(Post::class)
            ->whereRelation('author')
            ->count());

        // Related with specified model
        $user = $mapper->model(User::class)->where('name', 'Kenny')->first();
        $posts = $mapper->model(Post::class)->whereRelation('author', $user)->orderBy('id')->get();
        $this->assertCount(2, $posts);
        $this->assertEquals(6, $posts[0]->key);
        $this->assertEquals(12, $posts[1]->key);

        // Relation with clause
        $posts = $mapper
            ->model(Post::class)
            ->whereRelation('author', function (ModelQuery $query) {
                $query->where('name', '>', 'W');
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $posts);
        $this->assertEquals(4, $posts[0]->key);
        $this->assertEquals(10, $posts[1]->key);

        // Self relation
        $categories = $mapper
            ->model(Category::class)
            ->whereRelation('parent', function (ModelQuery $query) {
                $query->where('title', 'News');
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $categories);
        $this->assertEquals('Economics', $categories[0]->title);
        $this->assertEquals('Sport', $categories[1]->title);

        // Wrong specified model
        $this->assertException(RelationException::class, function () use ($mapper) {
            $mapper->model(Post::class)->whereRelation('author', new Category());
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('The given model ', $exception->getMessage());
        });

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($mapper) {
            $mapper->model(Post::class)->whereRelation('author', 'Jack');
        }, function (InvalidArgumentException $exception) {
            $this->assertStringStartsWith('The constraint argument expected to be ', $exception->getMessage());
        });
    }

    /**
     * Tests the `loadRelatives` method
     */
    public function testLoadRelatives()
    {
        $mapper = $this->makeMockDatabase();
        $relation = Post::author();

        $posts = $mapper->model(Post::class)->find([1, 5, 7, 11]);
        $relation->loadRelatives($mapper, 'author', $posts);
        foreach ($posts as $post) {
            $this->assertTrue(isset($post->author));
        }
        $this->assertEquals('Frank', $posts[0]->author->name);
        $this->assertEquals('Frank', $posts[1]->author->name);
        $this->assertEquals('Anny', $posts[2]->author->name);
        $this->assertNull($posts[3]->author);

        // With constraint
        $posts = $mapper->model(Post::class)->find([1, 5, 7, 11]);
        $relation->loadRelatives($mapper, 'author', $posts, function (ModelQuery $query) {
            $query->where('name', '<', 'F');
        });
        foreach ($posts as $post) {
            $this->assertTrue(isset($post->author));
        }
        $this->assertNull($posts[0]->author);
        $this->assertNull($posts[1]->author);
        $this->assertEquals('Anny', $posts[2]->author->name);
        $this->assertNull($posts[3]->author);

        // Skip models with loaded relatives
        $relation->loadRelatives($mapper, 'author', $posts, null, true);
        foreach ($posts as $post) {
            $this->assertTrue(isset($post->author));
        }
        $this->assertNull($posts[0]->author);
        $this->assertNull($posts[1]->author);
        $this->assertEquals('Anny', $posts[2]->author->name);
        $this->assertNull($posts[3]->author);

        // Models with null key value
        $post = new Post();
        $relation->loadRelatives($mapper, 'author', [$post]);
        $this->assertNull($post->author);

        // Incorrect key field value
        $post = new Post();
        $post->author_id = [1, 2];
        $this->assertException(IncorrectModelException::class, function () use ($relation, $mapper, $post) {
            $relation->loadRelatives($mapper, 'author', [$post]);
        }, function (IncorrectModelException $exception) {
            $this->assertEquals(
                'The model `author_id` field value expected to be scalar or null, array given',
                $exception->getMessage()
            );
        });
    }
}
