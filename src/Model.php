<?php

namespace Finesse\Wired;

/**
 * Model. The class represents a database table. An instance represents a table row.
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
     * Makes an empty self instance.
     *
     * @return static
     */
    public static function createEmpty(): self
    {
        return new static();
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
}
