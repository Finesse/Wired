<?php

namespace Finesse\Wired;

/**
 * Model. The class represents a database table. An instance represents a table row.
 *
 * All the model fields must be presented in a model class as public variables.
 *
 * @author Surgie
 */
abstract class Model
{
    /**
     * Return the model database table name (not prefixed)
     *
     * @return string
     */
    abstract public static function getTable(): string;

    /**
     * Returns the model identifier field name
     *
     * @return string
     */
    public static function getIdentifierField(): string
    {
        return 'id';
    }

    /**
     * Makes a self instance from a database row.
     *
     * @param array $row Row values. Indexed by column names.
     * @return static
     */
    public static function createFromRow(array $row): self
    {
        $model = static::createEmpty();

        foreach ($row as $field => $value) {
            $model->$field = $value;
        }

        return $model;
    }

    /**
     * Turns itself to a database row.
     *
     * @return array Row values indexed by column names
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
        return getInitialProperties(static::createEmpty());
    }
}

/**
 * Returns the list of not static object properties names. Moved out of the Model class to filter out not public
 * properties.
 *
 * @param object $object
 * @return string[]
 */
function getInitialProperties($object)
{
    return array_keys(get_object_vars($object));
}
