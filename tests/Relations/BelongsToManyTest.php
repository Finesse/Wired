<?php

namespace Finesse\Wired\Tests\Relations;

use Finesse\MiniDB\Query;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Helpers;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Tests\ModelsForTests\Category;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;

/**
 * Tests the BelongsToMany relation class
 *
 * @author Surgie
 */
class BelongsToManyTest extends TestCase
{
    /**
     * Tests the `applyToQueryWhere` method
     */
    public function testApplyToQueryWhere()
    {
        $mapper = $this->makeMockDatabase();

        // Relation with no constraints
        $relation = User::categories();
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query);
        $this->assertEquals(9, $query->count());

        // Related with specified model
        $category = $mapper->model(Category::class)->find(8);
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, $category);
        $users = $query->get();
        $this->assertCount(2, $users);
        $this->assertEquals('Jack', $users[0]->name);
        $this->assertEquals('Quentin', $users[1]->name);

        // Related with one of the given models
        $categories = $mapper->model(Category::class)->find([5, 8]);
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, $categories);
        $users = $query->orderBy('id')->get();
        $this->assertCount(3, $users);
        $this->assertEquals('Frank', $users[0]->name);
        $this->assertEquals('Jack', $users[1]->name);
        $this->assertEquals('Quentin', $users[2]->name);

        // Related with one of the given models (empty models list)
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, []);
        $users = $query->get();
        $this->assertCount(0, $users);

        // Relation with clause and column name collision
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, function (ModelQuery $query) {
            $query->where('id', '>=', 7);
        });
        $users = $query->get();
        $this->assertCount(4, $users);
        $this->assertEquals('Anny', $users[0]->name);
        $this->assertEquals('Jack', $users[1]->name);
        $this->assertEquals('Kenny', $users[2]->name);
        $this->assertEquals('Quentin', $users[3]->name);

        // Self relation
        $relation = User::followings();
        $query = $mapper->model(User::class);
        $relation->applyToQueryWhere($query, function (ModelQuery $query) {
            $query->where('name', 'Bob');
        });
        $users = $query->get();
        $this->assertCount(2, $users);
        $this->assertEquals('Anny', $users[0]->name);
        $this->assertEquals('Bob', $users[1]->name);

        // Wrong specified model
        $relation = User::categories();
        $query = $mapper->model(User::class);
        $this->assertException(IncorrectModelException::class, function () use ($relation, $query) {
            $relation->applyToQueryWhere($query, new Post);
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
        $this->assertException(IncorrectQueryException::class, function () use ($relation, $query) {
            $relation->applyToQueryWhere($query);
        }, function (IncorrectQueryException $exception) {
            $this->assertEquals('The given query doesn\'t have a context model', $exception->getMessage());
        });
    }

    /**
     * Tests the `loadRelatives` method
     */
    public function testLoadRelatives()
    {
        $mapper = $this->makeMockDatabase();

        $users = $mapper->model(User::class)->find([3, 6, 11, 24]);
        $relation = User::categories();
        $relation->loadRelatives($mapper, 'categories', $users);
        $this->assertEquals(['Football'], Helpers::getObjectsPropertyValues($users[0]->categories, 'title'));
        $this->assertEquals(['News', 'Hockey', 'Hockey'], Helpers::getObjectsPropertyValues($users[1]->categories, 'title'));
        $this->assertEquals(['Football', 'Lifehacks'], Helpers::getObjectsPropertyValues($users[2]->categories, 'title'));
        $this->assertEquals([], Helpers::getObjectsPropertyValues($users[3]->categories, 'title'));
        $this->assertSame($users[0]->categories[0], $users[2]->categories[0]);
        $this->assertEquals(['id' => 6, 'parent_id' => 4, 'title' => 'Football'], get_object_vars($users[0]->categories[0]));

        // Models with null key value
        $user = new User();
        $relation->loadRelatives($mapper, 'categories', [$user]);
        $this->assertCount(0, $user->categories);

        // Empty models list
        $relation->loadRelatives($mapper, 'categories', []);

        // Self related
        $users = $mapper->model(User::class)->find([1, 2, 3]);
        $relation = User::followings();
        $relation->loadRelatives($mapper, 'followings', $users);
        $this->assertEquals(['Bob', 'Charlie', 'Dick'], Helpers::getObjectsPropertyValues($users[0]->followings, 'name'));
        $this->assertEquals(['Anny', 'Frank', 'Bob'], Helpers::getObjectsPropertyValues($users[1]->followings, 'name'));
        $this->assertEquals(['Frank'], Helpers::getObjectsPropertyValues($users[2]->followings, 'name'));

        // With constraint and column name collision
        $users = $mapper->model(User::class)->find([1, 2, 3]);
        $relation->loadRelatives($mapper, 'followings', $users, function (ModelQuery $query) {
            $query->where('id', '>', 2);
        });
        $this->assertEquals(['Charlie', 'Dick'], Helpers::getObjectsPropertyValues($users[0]->followings, 'name'));
        $this->assertEquals(['Frank'], Helpers::getObjectsPropertyValues($users[1]->followings, 'name'));
        $this->assertEquals(['Frank'], Helpers::getObjectsPropertyValues($users[2]->followings, 'name'));
    }
}
