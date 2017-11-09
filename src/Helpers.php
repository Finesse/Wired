<?php

namespace Finesse\Wired;

use Finesse\Wired\Exceptions\NotModelException;

/**
 * Helper functions.
 *
 * @author Surgie
 */
class Helpers
{
    /**
     * Checks that the given class name is a model class.
     *
     * @param string $name Value name
     * @param string $className Class name
     * @throws NotModelException If the class is not a model
     */
    public static function checkModelClass(string $name, string $className)
    {
        if (!is_a($className, ModelInterface::class, true)) {
            throw new NotModelException(sprintf(
                '%s (%s) is not a model class implementation name (%s)',
                $name,
                $className,
                ModelInterface::class
            ));
        }
    }

    /**
     * Groups array of models by model classes. Checks that the given array values are models.
     *
     * @param ModelInterface[] $models
     * @return ModelInterface[][] The keys are the model class names. Doesn't contain empty arrays.
     * @throws NotModelException
     */
    public static function groupModelsByClass(array $models): array
    {
        $groups = [];

        foreach ($models as $index => $model) {
            if (!($model instanceof ModelInterface)) {
                throw new NotModelException('Argument $models['.$index.'] is not a model');
            }

            $groups[get_class($model)][] = $model;
        }

        return $groups;
    }

    /**
     * Index the given array of objects by an object property.
     *
     * @param array $objects
     * @param string $property
     * @return array The keys are the values of the property
     */
    public static function indexObjectsByProperty(array $objects, string $property): array
    {
        $result = [];

        foreach ($objects as $object) {
            $result[$object->$property] = $object;
        }

        return $result;
    }

    /**
     * Groups array of objects by a property values.
     *
     * @param array $objects
     * @param string $property
     * @return array[] The keys are the values of the properties. The values are the list of suitable objects.
     */
    public static function groupObjectsByProperty(array $objects, string $property): array
    {
        $groups = [];

        foreach ($objects as $object) {
            $groups[$object->$property][] = $object;
        }

        return $groups;
    }
}
