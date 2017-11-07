<?php

namespace Finesse\Wired\Tests;

use Finesse\Wired\Model;

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
}
