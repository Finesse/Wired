<?php

namespace Finesse\Wired\Tests\Relations;

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
            $this->assertStringStartsWith('The relation argument expected to be ', $exception->getMessage());
        });
    }
}
