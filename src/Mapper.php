<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\NotModelException;

/**
 * Mapper. Retrieves and saves models.
 *
 * @author Surgie
 */
class Mapper
{
    /**
     * @var Database Database on top of which the mapper runs
     */
    protected $database;

    /**
     * @param Database $database Database on top of which the mapper should run
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Makes a self instance from a database configuration array.
     *
     * @see Database::config Configuration array description
     * @param array $config
     * @return static
     * @throws DatabaseException
     */
    public static function create($config): self
    {
        try {
            return new static(Database::create($config));
        } catch (DBDatabaseException $exception) {
            throw new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Makes a model query builder.
     *
     * @param string $className Model class name
     * @return ModelQuery
     * @throws NotModelException
     */
    public function model(string $className): ModelQuery
    {
        $this->checkModelClass('The given model class', $className);

        /** @var Model $className */
        $query = $this->database->table($className::getTable());
        return new ModelQuery($query, $className);
    }

    /**
     * @return Database Underlying database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Checks that the given class name is a model class.
     *
     * @param string $name Value name
     * @param string $className Class name
     * @throws NotModelException If the class is not a model
     */
    protected function checkModelClass(string $name, string $className)
    {
        if (!is_a($className, Model::class, true)) {
            throw new NotModelException(sprintf(
                '%s (%s) is not a model class name implementation (%s)',
                $name,
                $className,
                Model::class
            ));
        }
    }
}
