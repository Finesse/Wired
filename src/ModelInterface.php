<?php

namespace Finesse\Wired;

/**
 * Model. The class represents a database table. An instance represents a table row.
 *
 * @author Sugrie
 */
interface ModelInterface
{
    /*
     * Database table mapping
     */

    /**
     * Return the model database table name (not prefixed)
     *
     * @return string
     */
    public static function getTable(): string;

    /**
     * Returns the model identifier field name
     *
     * @return string
     */
    public static function getIdentifierField(): string;

    /**
     * Makes a self instance from a database row.
     *
     * @param array $row Row values. Indexed by column names.
     * @return static
     */
    public static function createFromRow(array $row): self;

    /**
     * Turns itself to a database row.
     *
     * @return array Row values indexed by column names
     */
    public function convertToRow(): array;

    /**
     * Does the model instance exist in the database table.
     *
     * @return bool
     */
    public function doesExistInDatabase(): bool;


    /*
     * Relations
     */

    /**
     * Gets a model relation.
     *
     * @param string $name Relation name
     * @return RelationInterface|null Relation object or null, if the relation doesn't exist
     */
    public static function getRelation(string $name);

    /**
     * Sets a loaded relative model or models to this model instance.
     *
     * @param string $relationName Relation name
     * @param ModelInterface[]|ModelInterface|null $relative An array of relative instances (if relation is "-to-many"),
     *     a single instance (if relation is "-to-one") or null (if relation is "-to-one").
     */
    public function setLoadedRelatives(string $relationName, $relative);

    /**
     * Checks whether relative models are loaded and set to this model instance.
     *
     * It must distinguish a case when relatives are not set (returns false) and when it has no relatives (returns true).
     *
     * @param string $relationName Relation name
     * @return bool
     */
    public function doesHaveLoadedRelatives(string $relationName): bool;

    /**
     * Returns loaded and set relative models.
     *
     * @param string $relationName Relation name
     * @return ModelInterface[]|ModelInterface|null An array of relative instances (if relation is "-to-many"), a single
     *     instance (if relation is "-to-one") or null (if relation is "-to-one"). Returns null is the relatives are not
     *     loaded and set.
     */
    public function getLoadedRelatives(string $relationName);
}
