<?php

namespace Finesse\Wired;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Relations\AssociableRelationInterface;

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
     * Attaches a related model to this model (if the relation supports it). Add the attached model to the loaded
     * relatives of this model.
     *
     * @param string $relationName Relation name
     * @param ModelInterface $model Model to attach (this argument is required, it will have no default value in a
     *  future release)
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function associate(string $relationName, ModelInterface $model = null)
    {
        $relation = static::getRelationOrFail($relationName);

        if ($relation instanceof AssociableRelationInterface) {
            return $relation->associate($relationName, $this, $model);
        }

        throw new RelationException("Associating is not available for the `$relationName` relation");
    }

    /**
     * Detaches the related model from this model (if the relation supports it). Removes the attached model to from
     * loaded relatives of this model.
     *
     * @param string $relationName Relation name
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function dissociate(string $relationName)
    {
        $relation = static::getRelationOrFail($relationName);

        if ($relation instanceof AssociableRelationInterface) {
            return $relation->dissociate($relationName, $this);
        }

        throw new RelationException("Dissociating is not available for the `$relationName` relation");
    }

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
        if (!Helpers::canCallMethod(static::class, $name)) {
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
    public static function getRelationOrFail(string $name): RelationInterface
    {
        $relation = static::getRelation($name);

        if ($relation) {
            return $relation;
        }

        throw new RelationException(sprintf(
            'The relation `%s` is not defined in the %s model',
            $name,
            static::class
        ));
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
    public function unsetLoadedRelatives(string $relationName)
    {
        unset($this->loadedRelatives[$relationName]);
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
        return array_keys(Helpers::getObjectProperties(static::createEmpty()));
    }
}
