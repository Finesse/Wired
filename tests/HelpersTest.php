<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Helpers;
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
    }
}
