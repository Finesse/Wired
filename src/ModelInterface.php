<?php

namespace Finesse\Wired;

/**
 * Model. The class represents a database table. An instance represents a table row.
 *
 * @author Sugrie
 */
interface ModelInterface
{
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

    /**
     * Gets a model relation.
     *
     * @param string $name Relation name
     * @return RelationInterface|null Relation object or null, if the relation doesn't exist
     */
    public static function getRelation(string $name);
}
