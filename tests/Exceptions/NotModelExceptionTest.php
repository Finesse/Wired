<?php

namespace Finesse\Wired\Tests\Exceptions;

use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Tests\ModelsForTests\User;
use Finesse\Wired\Tests\TestCase;

/**
 * Tests the `NotModelException` class
 *
 * @author Surgie
 */
class NotModelExceptionTest extends TestCase
{
    /**
     * Tests the `checkModelClass` method
     */
    public function testCheckModelClass()
    {
        $this->assertEmpty(NotModelException::checkModelClass('Test', User::class));

        $this->assertException(NotModelException::class, function () {
            NotModelException::checkModelClass('Test value', 'foo bar');
        }, function (NotModelException $exception) {
            $this->assertEquals(
                'Test value (foo bar) is not a model class implementation name (Finesse\Wired\ModelInterface)',
                $exception->getMessage()
            );
        });
    }
}
