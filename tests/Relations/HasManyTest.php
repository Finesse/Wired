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
 * Tests the HasMany relation class
 *
 * @author Surgie
 */
class HasManyTest extends TestCase
{
    /**
     * Tests the `applyToQueryWhere` method
     */
    public function testApplyToQueryWhere()
    {
        $mapper = $this->makeMockDatabase();

        // Relation with no constraints
        $this->assertEquals(10, $mapper
            ->model(User::class)
            ->whereRelation('posts')
            ->count());

        // Related with specified model
        $post = $mapper->model(Post::class)->find(8);
        $users = $mapper->model(User::class)->whereRelation('posts', $post)->get();
        $this->assertCount(1, $users);
        $this->assertEquals('Quentin', $users[0]->name);

        // Relation with clause
        $users = $mapper
            ->model(User::class)
            ->whereRelation('posts', function (ModelQuery $query) {
                $query->where('created_at', '>=', mktime(19, 0, 0, 11, 7, 2017));
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(3, $users);
        $this->assertEquals('Charlie', $users[0]->name);
        $this->assertEquals('Frank', $users[1]->name);
        $this->assertEquals('Jack', $users[2]->name);

        // Self relation
        $categories = $mapper
            ->model(Category::class)
            ->whereRelation('children', function (ModelQuery $query) {
                $query->where('title', 'Hockey');
            })
            ->orderBy('id')
            ->get();
        $this->assertCount(1, $categories);
        $this->assertEquals('Sport', $categories[0]->title);

        // Wrong specified model
        $this->assertException(RelationException::class, function () use ($mapper) {
            $mapper->model(User::class)->whereRelation('posts', new Category());
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('The given model ', $exception->getMessage());
        });

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($mapper) {
            $mapper->model(User::class)->whereRelation('posts', 'foo');
        }, function (InvalidArgumentException $exception) {
            $this->assertStringStartsWith('The relation argument expected to be ', $exception->getMessage());
        });
    }
}
