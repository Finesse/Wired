<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Helpers;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\Tests\ModelsForTests\Category;
use Finesse\Wired\Tests\ModelsForTests\Post;
use Finesse\Wired\Tests\ModelsForTests\User;

/**
 * Tests the `Helpers` class
 *
 * @author Surgie
 */
class HelpersTest extends TestCase
{
    /**
     * Tests the `checkModelClass` method
     */
    public function testCheckModelClass()
    {
        $this->assertEmpty(Helpers::checkModelClass('Test', User::class));

        $this->assertException(NotModelException::class, function () {
            Helpers::checkModelClass('Test value', 'foo bar');
        }, function (NotModelException $exception) {
            $this->assertEquals(
                'Test value (foo bar) is not a model class implementation name (Finesse\Wired\ModelInterface)',
                $exception->getMessage()
            );
        });
    }

    /**
     * Tests the `checkModelObjectClass` method
     */
    public function testCheckModelObjectClass()
    {
        $this->assertEmpty(Helpers::checkModelObjectClass(new User, User::class));

        $this->assertException(IncorrectModelException::class, function () {
            Helpers::checkModelObjectClass(new User, Post::class);
        });

        $this->assertException(NotModelException::class, function () {
            Helpers::checkModelObjectClass(new \stdClass, User::class);
        });
        $this->assertException(NotModelException::class, function () {
            Helpers::checkModelObjectClass('Hello', User::class);
        });
    }

    /**
     * Tests the `groupModelsByClass` method
     */
    public function testGroupModelsByClass()
    {
        $this->assertEquals([], Helpers::groupModelsByClass([]));

        $user1 = new User();
        $user1->id = 8;
        $user2 = new User();
        $user2->id = 4;
        $post = new Post();
        $post->key = 5;
        $grouped = Helpers::groupModelsByClass([$user1, $post, $user2]);
        $this->assertEquals([User::class, Post::class], array_keys($grouped));
        $this->assertCount(2, $grouped[User::class]);
        $this->assertEquals(8, $grouped[User::class][0]->id);
        $this->assertEquals(4, $grouped[User::class][1]->id);
        $this->assertCount(1, $grouped[Post::class]);
        $this->assertEquals(5, $grouped[Post::class][0]->key);

        $this->assertException(NotModelException::class, function () {
            Helpers::groupModelsByClass([User::class, Post::class]);
        });
    }

    /**
     * Tests the `indexObjectsByProperty` method
     */
    public function testIndexObjectsByProperty()
    {
        $this->assertEquals([], Helpers::indexObjectsByProperty([], 'foo'));

        $user1 = new User();
        $user1->id = 8;
        $user1->name = 'Bill';
        $user2 = new User();
        $user2->id = 4;
        $user2->name = 'Ivan';
        $user3 = new User();
        $user3->id = 7;
        $user3->name = 'Bill';
        $users = Helpers::indexObjectsByProperty([$user1, $user2, $user3], 'name');
        $this->assertEquals(['Bill', 'Ivan'], array_keys($users));
        $this->assertEquals(7, $users['Bill']->id);
        $this->assertEquals(4, $users['Ivan']->id);
    }

    /**
     * Tests the `groupObjectsByProperty` method
     */
    public function testGroupObjectsByProperty()
    {
        $this->assertEquals([], Helpers::groupObjectsByProperty([], 'foo'));

        $user1 = new User();
        $user1->id = 8;
        $user1->name = 'Bill';
        $user2 = new User();
        $user2->id = 4;
        $user2->name = 'Ivan';
        $user3 = new User();
        $user3->id = 7;
        $user3->name = 'Bill';
        $groups = Helpers::groupObjectsByProperty([$user1, $user2, $user3], 'name');
        $this->assertEquals(['Bill', 'Ivan'], array_keys($groups));
        foreach ($groups as $users) {
            $this->assertInternalType('array', $users);
        }
        $this->assertCount(2, $groups['Bill']);
        $this->assertEquals(8, $groups['Bill'][0]->id);
        $this->assertEquals(7, $groups['Bill'][1]->id);
        $this->assertCount(1, $groups['Ivan']);
        $this->assertEquals(4, $groups['Ivan'][0]->id);

        $groups = Helpers::groupObjectsByProperty([
            'first' => $user1,
            'second' => $user2,
            'third' => $user3
        ], 'name', true);
        $this->assertEquals(['first', 'third'], array_keys($groups['Bill']));
        $this->assertEquals(['second'], array_keys($groups['Ivan']));
    }

    /**
     * Tests the `groupArraysByKey` method
     */
    public function testGroupArraysByKey()
    {
        $this->assertEquals([], Helpers::groupArraysByKey([], 'foo'));

        $users = [
            'first' => ['id' => 8, 'name' => 'Bill'],
            'second' => ['id' => 4, 'name' => 'Ivan'],
            'third' => ['id' => 7, 'name' => 'Bill'],
        ];
        $this->assertEquals(
            [
                'Bill' => [
                    ['id' => 8, 'name' => 'Bill'],
                    ['id' => 7, 'name' => 'Bill'],
                ],
                'Ivan' => [
                    ['id' => 4, 'name' => 'Ivan'],
                ],
            ],
            Helpers::groupArraysByKey($users, 'name')
        );
        $this->assertEquals(
            [
                'Bill' => [
                    'first' => ['id' => 8, 'name' => 'Bill'],
                    'third' => ['id' => 7, 'name' => 'Bill'],
                ],
                'Ivan' => [
                    'second' => ['id' => 4, 'name' => 'Ivan'],
                ],
            ],
            Helpers::groupArraysByKey($users, 'name', true)
        );
    }

    /**
     * Tests the `collectModelsRelatives` method
     */
    public function testCollectModelsRelatives()
    {
        $user1 = new User();
        $user1->name = 'user 1';
        $user2 = new User();
        $user2->name = 'user 2';
        $post2_1 = new Post();
        $post2_1->text = 'post 2 1';
        $post2_1->setLoadedRelatives('author', new User());
        $user2->setLoadedRelatives('posts', $post2_1);
        $user3 = new User();
        $post3_1 = new Post();
        $post3_1->text = 'post 3 1';
        $post3_1->setLoadedRelatives('author', new User());
        $post3_2 = new Post();
        $post3_2->text = 'post 3 2';
        $user3->setLoadedRelatives('posts', [$post3_1, $post3_2]);

        // Single relation
        $posts = Helpers::collectModelsRelatives([$user1, $user2, $user3], 'posts');
        $this->assertCount(3, $posts);
        $this->assertEquals($post2_1, $posts[0]);
        $this->assertEquals($post3_1, $posts[1]);
        $this->assertEquals($post3_2, $posts[2]);

        // Relations chain
        $users = Helpers::collectModelsRelatives([$user1, $user2, $user3], 'posts.author');
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertInstanceOf(User::class, $user);
        }

        // Not unique relatives
        $user = new User();
        $post1 = new Post();
        $post1->setLoadedRelatives('author', $user);
        $post2 = new Post();
        $post2->setLoadedRelatives('author', $user);
        $users = Helpers::collectModelsRelatives([$post1, $post2], 'author');
        $this->assertCount(1, $users);
        $this->assertEquals($user, $users[0]);
    }

    /**
     * Tests the `collectModelsCyclicRelatives` method
     */
    public function testCollectModelsCyclicRelatives()
    {
        $mapper = $this->makeMockDatabase();

        $category = $mapper->model(Category::class)->find(1);
        $mapper->loadCyclic($category, 'children');
        $allSubCategories = Helpers::collectModelsCyclicRelatives([$category], 'children');
        $this->assertCount(4, $allSubCategories);
        $this->assertEquals('Economics', $allSubCategories[0]->title);
        $this->assertEquals('Sport', $allSubCategories[1]->title);
        $this->assertEquals('Hockey', $allSubCategories[2]->title);
        $this->assertEquals('Football', $allSubCategories[3]->title);

        $category = $mapper->model(Category::class)->find(5);
        $mapper->loadCyclic($category, 'parent');
        $allParents = Helpers::collectModelsCyclicRelatives([$category], 'parent');
        $this->assertCount(2, $allParents);
        $this->assertEquals('Sport', $allParents[0]->title);
        $this->assertEquals('News', $allParents[1]->title);

        // Recursive relation
        $category1 = new Category();
        $category2 = new Category();
        $category1->setLoadedRelatives('parent', $category2);
        $category2->setLoadedRelatives('parent', $category1);
        $allParents = Helpers::collectModelsCyclicRelatives([$category1], 'parent');
        $this->assertCount(2, $allParents);
        $this->assertEquals($category2, $allParents[0]);
        $this->assertEquals($category1, $allParents[1]);
    }

    /**
     * Tests the `filterModelRelatives` and `filterModelsRelatives` methods
     */
    public function testFilterModelRelatives()
    {
        $user0 = new User();
        $user1 = new User();
        $user1->setLoadedRelatives('posts', new Post());
        $user2 = new User();
        $user2->setLoadedRelatives('posts', [new Post(), new Post(), new Post()]);

        $callCounter = 0;
        Helpers::filterModelsRelatives([$user0, $user1, $user2], 'posts', function (
            ModelInterface $relative, ModelInterface $model
        ) use (
            &$callCounter, $user0, $user1, $user2
        ) {
            switch (++$callCounter) {
                case 1:
                    $this->assertEquals($user1, $model);
                    $this->assertInstanceOf(Post::class, $relative);
                    $relative->text = 'Hello 1-0';
                    return true;
                case 2:
                    $this->assertEquals($user2, $model);
                    $this->assertInstanceOf(Post::class, $relative);
                    $relative->text = 'Hello 2-1';
                    return false;
                case 3:
                    $this->assertEquals($user2, $model);
                    $this->assertInstanceOf(Post::class, $relative);
                    $relative->text = 'Hello 2-2';
                    return $relative;
                case 4:
                    $this->assertEquals($user2, $model);
                    $this->assertInstanceOf(Post::class, $relative);
                    $relative->text = 'Hello 2-3';
                    $newRelative = new Post();
                    $newRelative->text = 'New hello 2-3';
                    return $newRelative;
                default:
                    $this->fail('The filter function is called too much times');
            }
        });

        $this->assertEquals(4, $callCounter);
        $this->assertFalse($user0->doesHaveLoadedRelatives('posts'));
        $this->assertEquals('Hello 1-0', $user1->posts->text);
        $this->assertCount(2, $user2->posts);
        $this->assertEquals('Hello 2-2', $user2->posts[0]->text);
        $this->assertEquals('New hello 2-3', $user2->posts[1]->text);
    }

    /**
     * Tests the `getObjectsPropertyValues` method
     */
    public function testGetObjectsPropertyValues()
    {
        $objects = ['one' => new \stdClass(), 'two' => new \stdClass(), 'three' => new \stdClass()];
        $objects['one']->value = 145;
        $objects['two']->title = 'miscellaneous';
        $objects['three']->value = 'very good';
        $objects['three']->title = 'spoon';
        $this->assertEquals(
            ['one' => 145, 'two' => null, 'three' => 'very good'],
            Helpers::getObjectsPropertyValues($objects, 'value')
        );
        $this->assertEquals(
            ['one' => 145, 'three' => 'very good'],
            Helpers::getObjectsPropertyValues($objects, 'value', true)
        );
    }

    /**
     * Tests the `getObjectProperties` method
     */
    public function testGetObjectProperties()
    {
        $object = new class {
            public $public = 'foo';
            protected $protected = 'bar';
            private $private = 'baz';
        };
        $object->public2 = 11;

        $this->assertEquals(['public' => 'foo', 'public2' => 11], Helpers::getObjectProperties($object));
    }

    /**
     * Tests the `doesMethodExist` method
     */
    public function testDoesMethodExist()
    {
        $object = new class {
            public function foo() {}
            protected function bar() {}
            private function baq() {}
            public static function fooStatic() {}
            protected static function barStatic() {}
            private static function baqStatic() {}
        };

        $this->assertTrue(Helpers::canCallMethod($object, 'foo'));
        $this->assertFalse(Helpers::canCallMethod($object, 'bar'));
        $this->assertFalse(Helpers::canCallMethod($object, 'baq'));
        $this->assertFalse(Helpers::canCallMethod($object, 'boo'));
        $this->assertTrue(Helpers::canCallMethod(get_class($object), 'fooStatic'));
        $this->assertFalse(Helpers::canCallMethod(get_class($object), 'barStatic'));
        $this->assertFalse(Helpers::canCallMethod(get_class($object), 'baqStatic'));
        $this->assertFalse(Helpers::canCallMethod(get_class($object), 'booStatic'));
    }

    /**
     * Tests the `getModelIdentifierField` method
     */
    public function testGetModelIdentifierField()
    {
        $this->assertEquals('key', Helpers::getModelIdentifierField(new Post));
        $this->assertEquals('id', Helpers::getModelIdentifierField(new User));
        $this->assertEquals('key', Helpers::getModelIdentifierField(Post::class));

        $mapper = $this->makeMockDatabase();
        $query = $mapper->model(Post::class);
        $this->assertEquals('key', Helpers::getModelIdentifierField($query));

        $subQuery = $query->makeSubQuery('foo');
        $this->assertException(IncorrectQueryException::class, function () use ($subQuery) {
            Helpers::getModelIdentifierField($subQuery);
        });

        $this->assertException(NotModelException::class, function () {
            Helpers::getModelIdentifierField(self::class);
        });

        $this->assertException(InvalidArgumentException::class, function () {
            Helpers::getModelIdentifierField(123);
        });
    }

    /**
     * Tests the `getFieldsToUpdate` method
     */
    public function testGetFieldsToUpdate()
    {
        $this->assertEquals([
            'bar' => '2',
            'baq' => 4,
        ], Helpers::getFieldsToUpdate([
            'foo' => 1,
            'bar' => 2,
            'baz' => '3',
        ], [
            'foo' => 1,
            'bar' => '2',
            'baq' => 4,
        ]));

        $this->assertEquals([], Helpers::getFieldsToUpdate([
            'foo' => 1,
            'bar' => 2,
        ], [
            'foo' => 1,
            'bar' => 2,
        ]));
    }
}
