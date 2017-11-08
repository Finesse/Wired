<?php

namespace Finesse\Wired;

/**
 * Model. The class represents a database table. An instance represents a table row.
 *
 * All the model fields must be presented in a model class as public variables.
 *
 * @author Surgie
 */
abstract class Model implements ModelInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getIdentifierField(): string
    {
        return 'id';
    }

    /**
     * {@inheritDoc}
     */
    public static function createFromRow(array $row): ModelInterface
    {
        $model = static::createEmpty();

        foreach ($row as $field => $value) {
            $model->$field = $value;
        }

        return $model;
    }

    /**
     * {@inheritDoc}
     */
    public function convertToRow(): array
    {
        $fields = static::getFields();
        $row = [];

        foreach ($fields as $field) {
            $row[$field] = $this->$field ?? null;
        }

        return $row;
    }

    /**
     * {@inheritDoc}
     */
    public function doesExistInDatabase(): bool
    {
        $identifierField = static::getIdentifierField();
        return isset($this->$identifierField);
    }

    /**
     * {@inheritDoc}
     */
    public static function getRelation(string $name)
    {
        if (!doesMethodExist(static::class, $name)) {
            return null;
        }

        $relation = static::$name();

        if ($relation instanceof RelationInterface) {
            return $relation;
        } else {
            return null;
        }
    }

    /**
     * Makes an empty self instance.
     *
     * @return static
     */
    protected static function createEmpty(): self
    {
        return new static();
    }

    /**
     * Returns the list of the model fields names.
     *
     * @return string[]
     */
    protected static function getFields(): array
    {
        return getObjectProperties(static::createEmpty());
    }
}

/**
 * Returns the list of not static object properties names. Moved out of the Model class to filter out not public
 * properties.
 *
 * @param object $object
 * @return string[]
 */
function getObjectProperties($object)
{
    return array_keys(get_object_vars($object));
}

/**
 * Does the same as the `method_exists` function. Moved out of the Model class to filter out not public methods.
 *
 * @see method_exists
 */
function doesMethodExist($object, string $name)
{
    return method_exists($object, $name);
}
