<?php

namespace Finesse\Wired\Tests\Relations;

use Finesse\MiniDB\Query;
use Finesse\Wired\Exceptions\IncorrectModelException;
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
        $relation = User::posts();
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query);
        $this->assertEquals(10, $query->count());

        // Related with specified model
        $post = $mapper->model(Post::class)->find(8);
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, $post);
        $users = $query->get();
        $this->assertCount(1, $users);
        $this->assertEquals('Quentin', $users[0]->name);

        // Relation with clause
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, function (ModelQuery $query) {
            $query->where('created_at', '>=', mktime(19, 0, 0, 11, 7, 2017));
        });
        $users = $query->orderBy('id')->get();
        $this->assertCount(3, $users);
        $this->assertEquals('Charlie', $users[0]->name);
        $this->assertEquals('Frank', $users[1]->name);
        $this->assertEquals('Jack', $users[2]->name);

        // Self relation
        $relation = Category::children();
        $query = $mapper->model(Category::class);
        $relation->applyToQueryWhere($query, function (ModelQuery $query) {
            $query->where('title', 'Hockey');
        });
        $categories = $query->orderBy('id')->get();
        $this->assertCount(1, $categories);
        $this->assertEquals('Sport', $categories[0]->title);

        // Wrong specified model
        $relation = User::posts();
        $query = $mapper->model(User::class);
        $this->assertException(RelationException::class, function () use ($relation, $query) {
            $relation->applyToQueryWhere($query, new Category());
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('The given model ', $exception->getMessage());
        });

        // Wrong argument
        $this->assertException(InvalidArgumentException::class, function () use ($relation, $query) {
            $relation->applyToQueryWhere($query, 'foo');
        }, function (InvalidArgumentException $exception) {
            $this->assertStringStartsWith('The constraint argument expected to be ', $exception->getMessage());
        });

        // Query without model
        $query = new ModelQuery(new class extends Query {
            public function __construct() {}
        });
        $this->assertException(RelationException::class, function () use ($relation, $query) {
            $relation->applyToQueryWhere($query);
        }, function (RelationException $exception) {
            $this->assertStringStartsWith('Can\'t get the subject model field name', $exception->getMessage());
        });
    }

    /**
     * Tests the `loadRelatives` method
     */
    public function testLoadRelatives()
    {
        $mapper = $this->makeMockDatabase();
        $relation = User::posts();

        $users = $mapper->model(Post::class)->find([6, 11, 15]);
        $relation->loadRelatives($mapper, 'posts', $users);
        foreach ($users as $user) {
            $this->assertInternalType('array', $user->posts);
        }
        $this->assertCount(4, $users[0]->posts);
        $this->assertCount(2, $users[1]->posts);
        $this->assertCount(0, $users[2]->posts);

        // With constraint
        $users = $mapper->model(Post::class)->find([6, 11, 15]);
        $relation->loadRelatives($mapper, 'posts', $users, function (ModelQuery $query) {
            $query
                ->where('key', '<', 10)
                ->orderBy('created_at', 'desc');
        });
        foreach ($users as $user) {
            $this->assertInternalType('array', $user->posts);
        }
        $this->assertCount(3, $users[0]->posts);
        $this->assertEquals(9, $users[0]->posts[0]->key);
        $this->assertEquals(5, $users[0]->posts[1]->key);
        $this->assertEquals(1, $users[0]->posts[2]->key);
        $this->assertCount(1, $users[1]->posts);
        $this->assertEquals(6, $users[1]->posts[0]->key);
        $this->assertCount(0, $users[2]->posts);

        // Skip models with loaded relatives
        $relation->loadRelatives($mapper, 'posts', $users, null, true);
        foreach ($users as $user) {
            $this->assertInternalType('array', $user->posts);
        }
        $this->assertCount(3, $users[0]->posts);
        $this->assertCount(1, $users[1]->posts);
        $this->assertCount(0, $users[2]->posts);

        // Models with null key value
        $user = new User();
        $relation->loadRelatives($mapper, 'posts', [$user]);
        $this->assertCount(0, $user->posts);

        // Incorrect key field value
        $user = new User();
        $user->id = [1, 2];
        $this->assertException(IncorrectModelException::class, function () use ($relation, $mapper, $user) {
            $relation->loadRelatives($mapper, 'posts', [$user]);
        }, function (IncorrectModelException $exception) {
            $this->assertEquals(
                'The model `id` field value expected to be scalar or null, array given',
                $exception->getMessage()
            );
        });
    }
}
