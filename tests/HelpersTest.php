<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Helpers;
use Finesse\Wired\ModelInterface;
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

    /**
     * Tests the `collectModelsRelatives` method
     */
    public function testCollectModelsRelatives()
    {
        $user1 = new User();
        $user2 = new User();
        $user2->setLoadedRelatives('posts', new Post());
        $user3 = new User();
        $user3->setLoadedRelatives('posts', [new Post(), new Post()]);

        $posts = Helpers::collectModelsRelatives([$user1, $user2, $user3], 'posts');
        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $this->assertInstanceOf(Post::class, $post);
        }
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
}
