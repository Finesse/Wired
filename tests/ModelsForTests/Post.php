<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsTo;

/**
 * Post
 *
 * @property-read User|null $author Post author (if loaded)
 * @property-read Category|null $category Post category (if loaded)
 *
 * @author Surgie
 */
class Post extends Model
{
    public $key;
    public $author_id;
    public $category_id;
    public $text;
    public $created_at;

    public static function getTable(): string
    {
        return 'posts';
    }

    public static function getIdentifierField(): string
    {
        return 'key';
    }

    public static function author()
    {
        return new BelongsTo(User::class, 'author_id');
    }

    public static function category()
    {
        return new BelongsTo(Category::class, 'category_id');
    }
}
