<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\MapperFeatures\AttachTrait;
use Finesse\Wired\MapperFeatures\LoadTrait;

/**
 * Mapper. Retrieves and saves models.
 *
 * @author Surgie
 */
class Mapper
{
    use LoadTrait, AttachTrait;

    const UPDATE = 'update';
    const REPLACE = 'replace';
    const DUPLICATE = 'duplicate';

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
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
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
        Helpers::checkModelClass('The given model class', $className);

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

        // Delete all models of a class in a single query
        foreach (Helpers::groupModelsByClass($models) as $sameClassModels) {
            $this->deleteModelsOfSameClass($sameClassModels);
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
        } catch (DBInvalidArgumentException $exception) {
            throw new IncorrectModelException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }

    /**
     * Deletes models of the same class from the database.
     *
     * @param ModelInterface[] $models Not empty array of models
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    protected function deleteModelsOfSameClass(array $models)
    {
        $sampleModel = reset($models);
        $identifierField = $sampleModel::getIdentifierField();
        $ids = [];

        foreach ($models as $model) {
            if ($model->doesExistInDatabase()) {
                $ids[] = $model->$identifierField;
                $model->$identifierField = null;
            }
        }

        if (empty($ids)) {
            return;
        }

        try {
            $this->database->table($sampleModel::getTable())->whereIn($identifierField, $ids)->delete();
        } catch (DBInvalidArgumentException $exception) {
            throw new IncorrectModelException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }
}
