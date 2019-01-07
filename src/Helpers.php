<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\ExceptionInterface as DBException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\MiniDB\Exceptions\InvalidReturnValueException as DBInvalidReturnValueException;
use Finesse\MiniDB\Query;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\ExceptionInterface;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
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
     * Checks that the given value is a model of the given class
     *
     * @param ModelInterface|mixed $model The value
     * @param string $expectedClassName Class name
     * @throws NotModelException If the given value is not a model object
     * @throws IncorrectModelException If the given model has a wrong class
     */
    public static function checkModelObjectClass($model, string $expectedClassName)
    {
        if ($model instanceof $expectedClassName) {
            return;
        }

        if ($model instanceof ModelInterface) {
            throw new IncorrectModelException(sprintf(
                'The given model %s is not a %s model',
                get_class($model),
                $expectedClassName
            ));
        }

        throw new NotModelException(sprintf(
            'The given value (%s) is not a model',
            is_object($model) ? get_class($model) : gettype($model)
        ));
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
     * @param bool $preserveKeys Must the input array keys be preserved in the output arrays
     * @return array[] The keys are the values of the properties. The values are the list of suitable objects.
     */
    public static function groupObjectsByProperty(array $objects, string $property, bool $preserveKeys = false): array
    {
        $groups = [];

        foreach ($objects as $index => $object) {
            $value = $object->$property;
            if ($preserveKeys) {
                $groups[$value][$index] = $object;
            } else {
                $groups[$value][] = $object;
            }
        }

        return $groups;
    }

    /**
     * Groups array of arrays by a key values.
     *
     * @param array[] $arrays
     * @param string $key
     * @param bool $preserveKeys Must the input array keys be preserved in the output arrays
     * @return array[][] The keys are the values of the keys. The values are the list of suitable arrays.
     */
    public static function groupArraysByKey(array $arrays, string $key, bool $preserveKeys = false): array
    {
        $groups = [];

        foreach ($arrays as $index => $array) {
            $value = $array[$key];
            if ($preserveKeys) {
                $groups[$value][$index] = $array;
            } else {
                $groups[$value][] = $array;
            }
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
     * @param bool $ignoreNull Should null values be ignored?
     * @return mixed[] The property values. THe indexes are the same as in the input array.
     */
    public static function getObjectsPropertyValues(array $objects, string $property, bool $ignoreNull = false): array
    {
        $values = [];

        foreach ($objects as $index => $object) {
            if (isset($object->$property)) {
                $values[$index] = $object->$property;
            } elseif (!$ignoreNull) {
                $values[$index] = null;
            }
        }

        return $values;
    }

    /**
     * Turns the given exception to a this package exception (if possible).
     *
     * @param \Throwable $exception
     * @return ExceptionInterface|\Throwable
     */
    public static function wrapException(\Throwable $exception): \Throwable
    {
        if ($exception instanceof ExceptionInterface) {
            return $exception;
        }

        if ($exception instanceof DBException) {
            if ($exception instanceof DBInvalidArgumentException) {
                return new InvalidArgumentException($exception->getMessage(), $exception->getCode(), $exception);
            }
            if ($exception instanceof DBInvalidReturnValueException) {
                return new InvalidReturnValueException($exception->getMessage(), $exception->getCode(), $exception);
            }
            if ($exception instanceof DBIncorrectQueryException) {
                return new IncorrectQueryException($exception->getMessage(), $exception->getCode(), $exception);
            }
            if ($exception instanceof DBDatabaseException) {
                return new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }

        return $exception;
    }

    /**
     * Gets object public properties names and values
     *
     * @param object $object
     * @return array The keys are the properties names and the values are the properties values
     */
    public static function getObjectProperties($object): array
    {
        return get_object_vars($object);
    }

    /**
     * Checks whether an object method can be called (exists and public).
     *
     * Warning! It returns true if a class name and a not-static method name is given. If you know how to fix it, PR or
     * issue is welcome.
     *
     * @param object|string $object An object or a class name
     * @param string $name The method name
     * @return bool
     */
    public static function canCallMethod($object, string $name): bool
    {
        return is_callable([$object, $name]);
    }

    /**
     * Gets the model identifier field name from a model instance, a model class name or a query object
     *
     * @param string|ModelQuery|ModelInterface $hint
     * @return string
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     * @throws NotModelException
     */
    public static function getModelIdentifierField($hint): string
    {
        if ($hint instanceof ModelInterface) {
            return $hint::getIdentifierField();
        }

        if ($hint instanceof ModelQuery) {
            $hint = $hint->getModelClass();
            if ($hint === null) {
                throw new IncorrectQueryException("The given query doesn't have a context model");
            }
        }

        if (is_string($hint)) {
            static::checkModelClass('The given string', $hint);
            /** @var ModelInterface $hint */
            return $hint::getIdentifierField();
        }

        throw new InvalidArgumentException(sprintf(
            'The argument expected to be a %s object, a % object or a model class name, %s given',
            ModelQuery::class,
            ModelInterface::class,
            is_object($hint) ? get_class($hint) : gettype($hint)
        ));
    }

    /**
     * Returns items of the $new array that are not presented in the $old array or which values differ
     *
     * @param array $old
     * @param array $new
     * @return array The keys are from the $new array, as well as the values
     */
    public static function getFieldsToUpdate(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old) || $value !== $old[$key]) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * Makes a value to pass to the ModelQuery::where method to constraint it with the given model field values
     *
     * @todo Test
     * @param array $fields
     * @return callable|array
     */
    public static function makeFieldsQueryCriterion(array $fields)
    {
        return function ($query) use ($fields) {
            /** @var Query $query */
            foreach ($fields as $name => $value) {
                if ($value === null) {
                    $query->whereNull($name);
                } else {
                    $query->where($name, $value);
                }
            }
        };
    }
}
