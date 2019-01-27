# Relations

Ralating models is describing family connections between models, 
e.g. claiming that a user can have any number of posts and a post can have not more then one author.
Related models can be easily retrieved from connections.

## Defining relations

### One to many

Add a method to a model class to tell the ORM that a model instance has many related instances of one type:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\HasMany;

class User extends Model
{
    public $id;
    // ...

    public static function posts()
    {
        return new HasMany(Post::class, 'user_id');
    }

    // ...
}
```

The `posts` method name is the relation name. You can set any name. The relation above tells that the `Post` model has 
the `user_id` field which contains a user instance identifier.

If you need the `user_id` field to point to another `User` field, pass it's name to the third `HasMany` argument:

```php
return new HasMany(Post::class, 'user_email', 'email');
``` 

### One to many (inverted)

Add a method to a model class to tell the ORM that a model instance belongs to one related instance:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsTo;

class Post extends Model
{
    public $user_id;
    // ...

    public static function author()
    {
        return new BelongsTo(User::class, 'user_id');
    }

    // ...
}
```

The `author` method name is the relation name. You can set any name. The relation above tells that the `Post` model has 
the `user_id` field which contains an author instance identifier.

If you need the `user_id` field to point to another `User` field, pass it's name to the third `BelongsTo` argument:

```php
return new BelongsTo(User::class, 'user_email', 'email');
```

### Many to many

Add a method to a model class to tell the ORM that a model instance belongs to many related instances and vice versa:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\BelongsToMany;

class Post extends Model
{
    // ...

    public static function tags()
    {
        return new BelongsToMany(Tag::class, 'post_id', 'post_tags', 'tag_id');
    }
}
```

The `tags` method name is the relation name. You can set any name. The relation above tells that there is a `post_tags`
table (called "pivot") that has two fields: `post_id` which contains a post identifier and `tag_id` which contains a tag
identifier.

If the pivot table fields point to specific models fields (not the identifier fields), pass the field names as arguments:

```php
return new BelongsToMany(Tag::class, 'post_uuid', 'post_tags', 'tag_name', 'uuid', 'name');
```


## Getting related models

Load all related models:

```php
$user = $orm->model(User::class)->find(14);
$orm->load($user, 'posts');
$posts = $user->posts;
```

The `'posts'` value is the relation name defined in the `User` model class. The `posts` property is added automatically
to a `User` object, you don't need to specify it in the model class.

Eager load related models for many models:

```php
$users = $orm->model(User::class)->get();
$orm->load($users, 'posts');

foreach ($users as $user) {
    foreach ($user->posts as $post) {
        // ...
    }
}
```

All the related models are loaded using a single SQL query like this `SELECT * FROM posts WHERE id IN (1, 2, 3, 4)`.

Load related models with a constraint or an order:

```php
$orm->load($users, 'posts', function ($query) {
    $query
        ->where('date', '<', '2015-01-01')
        ->orderBy('date', 'desc');
});
```

Load relative models only for the models that don't have loaded relatives:

```php
$orm->load($posts, 'author', null, true);
// ...
$orm->load($posts, 'author', null, true); // Doesn't load the second time 
```

Load relative models with relative submodels:

```php
$orm->load($post, 'author.posts.category'); // Relations are divided by dot

foreach ($post->author->posts as $sameAuthorPost) {
    $category = $sameAuthorPost->category;
}
```


## Relations in the query builder

Query all models having at least one related instance:

```php
$usersWithPosts = $orm
    ->model(User::class)
    ->whereRelation('posts') // The relation name specified in the User model class
    ->get();
    // Or ->delete() or ->update(...)
```

Query all models related with a model instance:

```php
$user = $orm->model(User::class)->find(12);
$userPosts = $orm
    ->model(Post::class)
    ->whereRelation('author', $user)
    ->get();
```

Query all models related with the given models:

```php
$specificUsers = $orm->model(User::class)->find([5, 15, 16]);
$specifitUsersPosts = $orm
    ->model(Post::class)
    ->whereRelation('author', $specificUsers)
    ->get();
```

Query all models having at least one related instance which fits a clause:

```php
$usersWithOldPosts = $orm
    ->model(User::class)
    ->whereRelation('posts', function ($query) {
        $query->where('date', '<', '2015-01-01');
    })
    ->get();
```

You can even filter using a complex relation chain:

```php
// All users having a post belonging to a category named "News" or "Events" (BTW, this is an example of many-to-many relation)
$reporters = $orm
    ->model(User::class)
    ->whereRelation('posts.category', function ($query) { // Relations are divided by dot
        $query->where('name', 'News')->orWhere('name', 'Events');
    })
    ->get();
```

You can also use the `orWhereRelation`, `whereNoRelation` and `orWhereNoRelation` methods.


## Attaching related models

### Many to many

There is the `attach` method to add attachments between models to the database:

```php
$mapper->attach($post, 'tags', $tags);
``` 

Where `tags` is a relation name of the `Post` model.

It adds records to the pivot table even if the models are already attached.
If you need to replace all the `$post` tags with the given tags, call the `setAttachments` method:

```php
$mapper->setAttachments($post, 'tags', $tags);
``` 

If you need to attach only such tags that are not attached, use the `setAttachments` with the corresponding argument:

```php
$mapper->setAttachments($post, 'tags', $tags, true);
``` 

It will make the database have all the previous and the new post tags and won't add duplicates.

You can set additional fields for the pivot table records (both methods support it):

```php
$mapper->setAttachments($post, 'tags', $tags, false, function ($post, $tag, $postIndex, $tagIndex) {
    return ['order' => $tagIndex];
});
```

Use the `detach` method to detach models:

```php
$mapper->detach($post, 'tags', $tags);
```

You can also detach all the post tags:

```php
$mapper->detachAll($post, 'tags');
```

### One to many

It doesn't support changing records in a database because there is an ambiguity, a detachment can be done multiple ways:

- "Many" side models are deleted
- "Many" side models get `null` as the foreign key field value

The decision depends on the business logic.

There are methods to help you do it manually. Set a foreign key field value:

```php
$user = $orm->model(User::class)->find(16);

$post = new Post();
$post->title = 'The Post';
// ...
$post->associate('author', $user); // 'author' is the relation name defined in the Post class
$orm->save($post);
```

Unset a foreign key field value:

```php
$post->dissociate('author'); // Or $post->associate('author', null);
$orm->save($post);
```

Warning! The `associate` and `dessociate` methods don't save models to the database, you need to do it manually.


## Cyclic relations

Suppose there is a self related model like this:

```php
use Finesse\Wired\Model;
use Finesse\Wired\Relations\HasMany;

class Category extends Model
{
    public $id;
    public $parent_id;
    public $name;

    // ...
    
    public static function subcategories()
    {
        return new HasMany(self::class, 'parent_id');
    }
    
    public static function parent()
    {
        return new BelongsTo(self::class, 'parent_id');
    }
}
```

### Getting related models recursively

You can load all the categories tree:

```php
$category = $orm->model(Category::class)->find(1);
$orm->loadCyclic($category, 'subcategories');

/*
    $category->subcategories = [
        Category(subcategories = [
            Category(subcategories = [
                ...
            ]),
            ...
        ]),
        Category(subcategories = [
            ...
        ]),
        ...
    ]
 */
```

Or the category parents chain:

```php
$category = $orm->model(Category::class)->find(35);
$orm->loadCyclic($category, 'parent');

/*
    $category->parent = Category(parent = Category(parent = ...))
 */
```

The `loadCyclic` method supports all the other arguments supported by the `load` method.
