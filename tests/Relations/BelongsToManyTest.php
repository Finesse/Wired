<?php

namespace Finesse\Wired\Tests\Relations;

use Finesse\MiniDB\Query;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\Relations\BelongsToMany;
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

    /**
     * Tests the `attach` method
     */
    public function testAttach()
    {
        $mapper = $this->makeMockDatabase();

        // Replace and detach
        $relation = User::followings();
        $followers = $mapper->model(User::class)->find([2, 3]);
        $followings = $mapper->model(User::class)->find([6, 7]);
        $relation->attach($mapper, $followers, $followings, Mapper::REPLACE, true);
        $this->assertEquals([
            ['id' =>  9, 'led_id' => 2, 'lead_id' => 6],
            ['id' => 10, 'led_id' => 2, 'lead_id' => 7],
            ['id' => 11, 'led_id' => 3, 'lead_id' => 6],
            ['id' => 12, 'led_id' => 3, 'lead_id' => 7],
        ], $mapper->getDatabase()
            ->table('follows')
            ->whereIn('led_id', [2, 3])
            ->orderBy('id')
            ->get()
        );

        // Duplicate, no detach, with extra fields
        $relation = User::categories();
        $getExtraFields = function (User $user, Category $category, $userKey, $categoryKey) {
            return [
                'created_at' => time() - rand(1000, 100000),
                'text' => "$user->name with order $userKey has posted to $category->title with order $categoryKey"
            ];
        };
        $users = [
            'uno' => $mapper->model(User::class)->find(11)
        ];
        $categories = array_combine(
            ['one', 'two'],
            $mapper->model(Category::class)->find([2, 7])
        );
        $relation->attach($mapper, $users, $categories, Mapper::DUPLICATE, false, $getExtraFields);
        $posts = $mapper
            ->model(Post::class)
            ->whereRelation('author', $users)
            ->orderBy('key')
            ->get();
        $this->assertCount(4, $posts);
        $this->assertAttributes([
            'category_id' => $categories['two']->id,
            'text' => 'Kenny with order uno has posted to Lifehacks with order two'
        ], $posts[3]);

        // Duplicate and detach
        $relation = User::followings();
        $follower = $mapper->model(User::class)->find(3);
        $followings = $mapper->model(User::class)->find([7, 8]);
        $relation->attach($mapper, [$follower], $followings, Mapper::DUPLICATE, true);
        $this->assertEquals([
            ['id' => 12, 'led_id' => 3, 'lead_id' => 7],
            ['id' => 13, 'led_id' => 3, 'lead_id' => 7],
            ['id' => 14, 'led_id' => 3, 'lead_id' => 8],
        ], $mapper->getDatabase()
            ->table('follows')
            ->where('led_id', $follower->id)
            ->orderBy('id')
            ->get()
        );

        // Empty models lists
        $attachmentsCount = $mapper->getDatabase()->table('follows')->count();
        $relation->attach($mapper, [], [], Mapper::DUPLICATE, false);
        $this->assertEquals($attachmentsCount, $mapper->getDatabase()->table('follows')->count());

        // Invalid extra fields return value
        $this->assertException(InvalidReturnValueException::class, function () use ($relation, $mapper, $follower, $followings) {
            $relation->attach($mapper, [$follower], $followings, Mapper::DUPLICATE, false, function () {
                return 'foo';
            });
        });

        // Wrong child model
        $this->assertException(IncorrectModelException::class, function () use ($relation, $mapper, $follower) {
            $relation->attach($mapper, [$follower], [new Post], Mapper::DUPLICATE, false);
        });

        // Database error
        $relation = new BelongsToMany(User::class, 'user1_id', 'missing_table', 'user2_id');
        $this->assertException(DatabaseException::class, function () use ($relation, $mapper, $follower, $followings) {
            $relation->attach($mapper, [$follower], $followings, Mapper::DUPLICATE, false);
        });

        // Unexpected "on match" argument
        $this->assertException(InvalidArgumentException::class, function () use ($relation, $mapper, $follower, $followings) {
            $relation->attach($mapper, [$follower], $followings, 'foobar', false);
        }, function (InvalidArgumentException $exception) {
            $this->assertContains('unexpected $onMatch value', $exception->getMessage());
        });
    }

    /**
     * Tests the `attach` method with update on match
     */
    public function testAttachWithUpdate()
    {
        $mapper = $this->makeMockDatabase();
        $getPostExtraFields = function (User $user, Category $category, $userKey, $categoryKey) {
            return [
                'created_at' => time() - rand(1000, 100000),
                'text' => "$user->name with key $userKey has posted to $category->title with key $categoryKey"
            ];
        };

        // Ordinary case with custom relation fields
        $postsCount = $mapper->model(Post::class)->count();
        $users = array_combine(
            ['bob', 'frank'],
            $mapper->model(User::class)->find([2, 6])
        );
        $categories = array_combine(
            ['news', 'hockey'],
            $mapper->model(Category::class)->find([1, 5])
        );
        $relation = User::categories();
        $relation->attach($mapper, $users, $categories, Mapper::UPDATE, true, $getPostExtraFields);
        $this->assertEquals($postsCount, $mapper->model(Post::class)->count());
        $posts = $mapper->model(Post::class)->whereRelation('author', $users)->orderBy('key')->get();
        $this->assertCount(4, $posts);
        $this->assertAttributes(['key' => 1, 'author_id' => 6, 'category_id' => 1, 'text' => 'Frank with key frank has posted to News with key news'], $posts[0]);
        $this->assertAttributes(['key' => 5, 'author_id' => 6, 'category_id' => 5, 'text' => 'Frank with key frank has posted to Hockey with key hockey'], $posts[1]);
        $this->assertAttributes(['key' => 17, 'author_id' => 2, 'category_id' => 1, 'text' => 'Bob with key bob has posted to News with key news'], $posts[2]);
        $this->assertAttributes(['key' => 18, 'author_id' => 2, 'category_id' => 5, 'text' => 'Bob with key bob has posted to Hockey with key hockey'], $posts[3]);

        // Repeating models
        $relation->attach($mapper, [$users['bob']], array_fill(0, 3, $categories['news']), Mapper::UPDATE, false, $getPostExtraFields);
        $this->assertEquals($postsCount + 2, $mapper->model(Post::class)->count());
        $posts = $mapper->model(Post::class)->whereRelation('author', $users['bob'])->orderBy('key')->get();
        $this->assertCount(4, $posts);
        $this->assertAttributes(['key' => 17, 'category_id' => 1], $posts[0]);
        $this->assertAttributes(['key' => 18, 'category_id' => 5], $posts[1]);
        $this->assertAttributes(['key' => 19, 'category_id' => 1], $posts[2]);
        $this->assertAttributes(['key' => 20, 'category_id' => 1], $posts[3]);

        // Update one attachment and keep other between 2 models that are attached multiple times
        $relation->attach($mapper, [$users['bob']], [$categories['news']], Mapper::UPDATE, false, function () {
            return ['text' => 'Update'];
        });
        $this->assertEquals($postsCount + 2, $mapper->model(Post::class)->count());
        $posts = $mapper->model(Post::class)->whereRelation('author', $users['bob'])->whereRelation('category', $categories['news'])->orderBy('key')->get();
        $this->assertCount(3, $posts);
        $this->assertAttributes(['key' => 17, 'text' => 'Update'], $posts[0]);
        $this->assertAttributeEquals(19, 'key', $posts[1]);
        $this->assertAttributeNotEquals('Update', 'text', $posts[1]);
        $this->assertAttributeEquals(20, 'key', $posts[2]);
        $this->assertAttributeNotEquals('Update', 'text', $posts[2]);

        // Non-changing attachment
        $relation = User::followings();
        $follows = $mapper->getDatabase()->table('follows')->orderBy('id')->get();
        $follower = $mapper->model(User::class)->find(1);
        $followings = $mapper->model(User::class)->find([2, 3, 4]);
        $relation->attach($mapper, [$follower], $followings, Mapper::UPDATE, true);
        $this->assertEquals($follows, $mapper->getDatabase()->table('follows')->orderBy('id')->get());
    }

    /**
     * Tests the `detach` method
     */
    public function testDetach()
    {
        $mapper = $this->makeMockDatabase();

        $relation = User::categories();
        $users = $mapper->model(User::class)->find([1, 6, 11]);
        $categories = $mapper->model(Category::class)->find([5, 6]);
        $relation->detach($mapper, $users, $categories);
        $posts = $mapper
            ->model(Post::class)
            ->addSelect('key')
            ->whereRelation('author', $users)
            ->orWhereRelation('category', $categories)
            ->get();
        $this->assertEquals([1, 7, 12, 14, 17], Helpers::getObjectsPropertyValues($posts, 'key'));

        // Empty model list
        $attachmentsCount = $mapper->model(Post::class)->count();
        $relation->detach($mapper, $users, []);
        $this->assertEquals($attachmentsCount, $mapper->model(Post::class)->count());
        $relation->detach($mapper, [], $categories);
        $this->assertEquals($attachmentsCount, $mapper->model(Post::class)->count());

        // Detach all child models
        $relation->detach($mapper, $users);
        $this->assertEquals($attachmentsCount - 4, $mapper->model(Post::class)->count());
        $this->assertEmpty($mapper->model(Category::class)->whereRelation('authors', $users)->get());

        // Wrong child model
        $this->assertException(IncorrectModelException::class, function () use ($relation, $mapper, $users) {
            $relation->detach($mapper, $users, [new Post]);
        });

        // Database error
        $relation = new BelongsToMany(Category::class, 'user_id', 'missing_table', 'category_id');
        $this->assertException(DatabaseException::class, function () use ($relation, $mapper, $users) {
            $relation->detach($mapper, $users);
        });
    }
}
