<?php

namespace Finesse\Wired;

/**
 * {@inheritDoc}
 *
 * A basic model interface implementation.
 *
 * All the model fields must be presented in a model class as public variables.
 *
 * @author Surgie
 */
abstract class Model implements ModelInterface
{
    /**
     * @var array Loaded and set instance relative instances. The keys are the relations names. The values may have the
     *     following types: ModelInterface[] if relation is "-to-many", ModelInterface if relation is "-to-one" or null
     *     if relation is "-to-one".
     */
    protected $loadedRelatives = [];

    /**
     * {@inheritDoc}
     * Returns true if has loaded relations with the given name.
     */
    public function __isset($name)
    {
        return $this->doesHaveLoadedRelatives($name);
    }

    /**
     * {@inheritDoc}
     * Returns loaded relations if has.
     *
     * @return ModelInterface[]|ModelInterface|null
     * @throws \Error If the given property doesn't exist
     */
    public function __get($name)
    {
        if ($this->doesHaveLoadedRelatives($name)) {
            return $this->getLoadedRelatives($name);
        }

        throw new \Error(sprintf('Undefined property: %s::$%s', static::class, $name));
    }

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
     * {@inheritDoc}
     */
    public function setLoadedRelatives(string $relationName, $relative)
    {
        $this->loadedRelatives[$relationName] = $relative;
    }

    /**
     * {@inheritDoc}
     */
    public function doesHaveLoadedRelatives(string $relationName): bool
    {
        return array_key_exists($relationName, $this->loadedRelatives);
    }

    /**
     * {@inheritDoc}
     */
    public function getLoadedRelatives(string $relationName)
    {
        return $this->loadedRelatives[$relationName] ?? null;
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
