<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;

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

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'users';
    }
}
