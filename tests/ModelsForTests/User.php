<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\HasMany;

/**
 * User
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
}
