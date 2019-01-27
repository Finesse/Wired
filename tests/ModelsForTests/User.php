<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsToMany;
use Finesse\Wired\Relations\HasMany;

/**
 * User
 *
 * @property-read Post[] $posts User posts (if loaded)
 * @property-read Category[] $categories The categories where user has added a post (if loaded)
 * @property-read User[] $followers Users who follow this user (if loaded)
 * @property-read User[] $followings Users that this user follows (if loaded)
 *
 * @author Surgie
 */
class User extends Model
{
    public $id;
    public $name;
    public $email;

    public static function getTable(): string
    {
        return 'users';
    }

    public static function posts()
    {
        return new HasMany(Post::class, 'author_id');
    }

    public static function categories()
    {
        return new BelongsToMany(Category::class, 'author_id', 'posts', 'category_id');
    }

    public static function followers()
    {
        return new BelongsToMany(self::class, 'lead_id', 'follows', 'led_id');
    }

    public static function followings()
    {
        return new BelongsToMany(self::class, 'led_id', 'follows', 'lead_id');
    }
}
