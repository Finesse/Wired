<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Model;
use Finesse\Wired\RelationInterface;
use Finesse\Wired\Relations\BelongsTo;
use Finesse\Wired\Tests\ModelsForTests\User;

/**
 * Tests the Model class
 *
 * @author Surgie
 */
class ModelTest extends TestCase
{
    /**
     * Tests the `createFromRow` method
     */
    public function testCreateFromRow()
    {
        $model = new class extends Model {
            public $foo = 1;
            public $bar = 2;
            public static function getTable(): string {
                return 'test';
            }
        };

        $model1 = $model::createFromRow(['foo' => 5, 'baz' => 10]);
        $this->assertCount(3, get_object_vars($model1));
        $this->assertAttributes(['foo' => 5, 'bar' => 2, 'baz' => 10], $model1);
    }

    /**
     * Tests the `convertToRow` method
     */
    public function testConvertToRow()
    {
        $model = new class extends Model {
            public $foo;
            public $bar = 5;
            public $baz = 'zzz';
            public static function getTable(): string {
                return 'test';
            }
        };
        $model->baz = 'abc';
        $model->foo_count = 10;

        $this->assertEquals(['foo' => null, 'bar' => 5, 'baz' => 'abc'], $model->convertToRow());
    }

    /**
     * Tests the `doesExistInDatabase` method
     */
    public function testDoesExistInDatabase()
    {
        $user = new User();
        $this->assertFalse($user->doesExistInDatabase());

        $user->id = 5;
        $this->assertTrue($user->doesExistInDatabase());
    }

    /**
     * Tests the `getRelation` method
     */
    public function testGetRelation()
    {
        $model = new class extends Model {
            public static function getTable(): string {
                return 'test';
            }
            public static function parent() {
                return new BelongsTo(User::class, 'bar');
            }
            public static function notARelation() {
                return 'foo bar';
            }
        };

        $this->assertInstanceOf(RelationInterface::class, $model::getRelation('parent'));
        $this->assertNull($model::getRelation('notARelation'));
        $this->assertNull($model::getRelation('foo'));
    }
}
