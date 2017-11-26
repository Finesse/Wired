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

    /**
     * Collects models related models to a plain array. The array has only unique models.
     *
     * @param ModelInterface[] $models The models
     * @param string $relation Relation name from which the relative models should be taken. If you need to collect
     *     relatives from a subrelation, add it's name separated by a dot.
     * @return ModelInterface[]
     */
    public static function collectModelsRelatives(array $models, string $relation): array
    {
        foreach (explode('.', $relation) as $relation) {
            $nextModels = [];

            foreach ($models as $model) {
                $relatives = $model->getLoadedRelatives($relation);

                if (is_array($relatives)) {
                    foreach ($relatives as $relative) {
                        // We can't index the relatives list by models identifiers because a model can have no identifier
                        // We can't use array_unique because it uses not strict comparison
                        // Using spl_object_hash is the faster than using in_array: https://3v4l.org/9B7nD
                        $nextModels[spl_object_hash($relative)] = $relative;
                    }
                } elseif ($relatives instanceof ModelInterface) {
                    $nextModels[spl_object_hash($relatives)] = $relatives;
                }
            }

            $models = $nextModels;
        }

        return array_values($models);
    }

    /**
     * Collects models related models, related models related models and so on to a plain array.
     *
     * @param ModelInterface[] $models The models
     * @param string $relation Relation name from which the relative models should be taken. If you need to collect
     *     relatives from a subrelation, add it's name separated by a dot.
     * @return ModelInterface[]
     */
    public static function collectModelsCyclicRelatives(array $models, string $relation): array
    {
        $relatives = [];

        while ($models) {
            $nextLevelModels = static::collectModelsRelatives($models, $relation);

            foreach ($nextLevelModels as $index => $model) {
                // We can't index the relatives list by models identifiers because a model can have no identifier
                // Using spl_object_hash is the faster than using in_array: https://3v4l.org/9B7nD
                $modelHash = spl_object_hash($model);

                if (isset($relatives[$modelHash])) {
                    // If a related model is already in the relatives list, it's relatives are not collected the
                    // second time. It prevents an infinite loop.
                    unset($nextLevelModels[$index]);
                } else {
                    $relatives[$modelHash] = $model;
                }
            }

            $models = $nextLevelModels;
        }

        return array_values($relatives);
    }

    /**
     * Filter relatives lists of a models list.
     *
     * @param ModelInterface[] $models The models list
     * @param string $relation Name of relation which relatives should be filtered
     * @param callable $filter Filter function. Called on each relative model. Takes a relative model as the first
     *     argument and a corresponding model from the list as the second argument. Return values: true — leave the
     *     model, false — remove the model from the relatives list, ModelInterface — replace the relative model with the
     *     given model.
     */
    public static function filterModelsRelatives(array $models, string $relation, callable $filter)
    {
        foreach ($models as $model) {
            static::filterModelRelatives($model, $relation, function (ModelInterface $relative) use ($filter, $model) {
                return $filter($relative, $model);
            });
        }
    }

    /**
     * Filters a model relatives list.
     *
     * @param ModelInterface $model The model
     * @param string $relation Name of relation which relatives should be filtered
     * @param callable $filter Filter function. Called on each relative model. Takes a relative model as the first
     *     argument. Return values: true — leave the model, false — remove the model from the relatives list,
     *     ModelInterface — replace the relative model with the given model.
     */
    public static function filterModelRelatives(ModelInterface $model, string $relation, callable $filter)
    {
        if (!$model->doesHaveLoadedRelatives($relation)) {
            return;
        }

        $relatives = $model->getLoadedRelatives($relation);

        if ($relatives === null) {
            $relatives = [];
            $areRelativesMultiple = false;
        } elseif (is_array($relatives)) {
            $areRelativesMultiple = true;
        } else {
            $relatives = [$relatives];
            $areRelativesMultiple = false;
        }

        /** @var ModelInterface[] $relatives */
        $areRelativesChanged = false;

        foreach ($relatives as $index => $relative) {
            $newRelative = $filter($relative);

            if ($newRelative === true) {
                continue;
            }
            if ($newRelative === false) {
                unset($relatives[$index]);
                $areRelativesChanged = true;
                continue;
            }
            if ($newRelative !== $relative) {
                $relatives[$index] = $newRelative;
                $areRelativesChanged = true;
                continue;
            }
        }

        if ($areRelativesChanged) {
            $model->setLoadedRelatives(
                $relation,
                $areRelativesMultiple ? array_values($relatives) : $relatives[0] ?? null
            );
        }
    }

    /**
     * Convert an array of objects to an array of the objects property values.
     *
     * @param array $objects The objects
     * @param string $property The property name
     * @return mixed[] The property values. THe indexes are the same as in the input array.
     */
    public static function getObjectsPropertyValues(array $objects, string $property): array
    {
        $values = [];

        // foreach is faster than array_map: https://3v4l.org/0uGlg
        foreach ($objects as $index => $object) {
            $values[$index] = $object->$property ?? null;
        }

        return $values;
    }
}
