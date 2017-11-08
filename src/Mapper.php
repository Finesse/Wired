<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;

/**
 * Mapper. Retrieves and saves models.
 *
 * Relations features:
 *  * todo: Eager loading related models
 *  * todo: Associate a related model with a model
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
     * @see Database::create Configuration array description
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
        NotModelException::checkModelClass('The given model class', $className);

        /** @var ModelInterface $className */
        $query = $this->database->table($className::getTable());
        return new ModelQuery($query, $className);
    }

    /**
     * Saves the given models to the database.
     *
     * @param ModelInterface|ModelInterface[] A model or an array of models
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function save($models)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach ($models as $index => $model) {
            $this->saveModel($model);
        }
    }

    /**
     * Deletes the given models from the database.
     *
     * @param ModelInterface|ModelInterface[] $models A model or an array of models
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function delete($models)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        // Group models by class
        /** @var ModelInterface[][] $groups */
        $groups = [];
        foreach ($models as $index => $model) {
            if (!($model instanceof ModelInterface)) {
                throw new NotModelException('Argument $models['.$index.'] is not a model');
            }

            $groups[get_class($model)][] = $model;
        }

        // Delete all models in a group in a single query
        foreach ($groups as $class => $models) {
            /** @var ModelInterface $class */
            $identifierField = $class::getIdentifierField();
            $ids = [];

            foreach ($models as $model) {
                if ($model->doesExistInDatabase()) {
                    $ids[] = $model->$identifierField;
                    $model->$identifierField = null;
                }
            }

            if (empty($ids)) {
                continue;
            }

            try {
                $this->database->table($class::getTable())->whereIn($identifierField, $ids)->delete();
            } catch (DBDatabaseException $exception) {
                throw new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
            } catch (DBInvalidArgumentException $exception) {
                throw new IncorrectModelException($exception->getMessage(), $exception->getCode(), $exception);
            }
        }
    }

    /**
     * @return Database Underlying database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Saves a single model to the database.
     *
     * @param ModelInterface $model
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    protected function saveModel(ModelInterface $model)
    {
        $identifierField = $model::getIdentifierField();
        $row = $model->convertToRow();
        unset($row[$identifierField]);

        try {
            if ($model->doesExistInDatabase()) {
                $this->database
                    ->table($model::getTable())
                    ->where($identifierField, $model->$identifierField)
                    ->update($row);
            } else {
                $model->$identifierField = $this->database
                    ->table($model::getTable())
                    ->insertGetId($row, $model->$identifierField);
            }
        } catch (DBDatabaseException $exception) {
            throw new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (DBInvalidArgumentException $exception) {
            throw new IncorrectModelException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
