<?php

namespace Finesse\Wired\Tests\ModelsForTests;

use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsTo;

/**
 * Post
 *
 * @author Surgie
 */
class Post extends Model
{
    public $id;
    public $author_id;
    public $category_id;
    public $text;
    public $created_at;

    public static function getTable(): string
    {
        return 'posts';
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
