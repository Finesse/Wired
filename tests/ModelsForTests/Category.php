<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsTo;
use Finesse\Wired\Relations\BelongsToMany;
use Finesse\Wired\Relations\HasMany;

/**
 * Category
 *
 * @property-read Category|null $parent Parent category (if loaded)
 * @property-read Category[] $children Child categories (if loaded)
 * @property-read Post[] $posts Category posts (if loaded)
 * @property-read User[] $authors Category posts authors (if loaded)
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

    public static function authors()
    {
        return new BelongsToMany(User::class, 'category_id', 'posts', 'author_id');
    }
}
