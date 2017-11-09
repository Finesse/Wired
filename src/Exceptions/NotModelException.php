<?php

namespace Finesse\Wired\Exceptions;

use Finesse\Wired\ModelInterface;

/**
 * The given value is not a model or a model class name.
 *
 * @author Surgie
 */
class NotModelException extends ModelException
{
    /**
     * Checks that the given class name is a model class.
     *
     * @param string $name Value name
     * @param string $className Class name
     * @throws self If the class is not a model
     */
    public static function checkModelClass(string $name, string $className)
    {
        if (!is_a($className, ModelInterface::class, true)) {
            throw new static(sprintf(
                '%s (%s) is not a model class implementation name (%s)',
                $name,
                $className,
                ModelInterface::class
            ));
        }
    }
}
