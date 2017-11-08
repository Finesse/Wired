<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsTo;
use Finesse\Wired\Relations\HasMany;

/**
 * Category
 *
 * @author Surgie
 */
class Category extends Model
{
    public $id;
    public $parent_id;
    public $title;

    public static function getTable(): string
    {
        return 'categories';
    }

    public static function parent()
    {
        return new BelongsTo(self::class, 'parent_id');
    }

    public static function children()
    {
        return new HasMany(self::class, 'parent_id');
    }

    public static function posts()
    {
        return new HasMany(Post::class, 'category_id');
    }
}
