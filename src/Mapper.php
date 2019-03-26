<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Database;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
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
    const AUTO = 'auto';
    const ADD = 'add';
    const ADD_AND_KEEP_ID = 'addAndKeepIdentifier';

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
     * @param string $mode How to treat existing and not existing models in the database:
     *  - 'add' or Mapper::ADD - save the model as a new row with the identifier given by the database (auto increment);
     *  - 'addAndKeepIdentifiers' or Mapper::ADD_AND_KEEP_ID - save the model as a new row with the identifier stored
     *      in the model object. Warning, an error may occur if the given identifier exists in the database;
     *  - 'update' or Mapper::UPDATE - update the existing row, never create a new row;
     *  - 'auto' or Mapper::AUTO - update the existing row if the model object has identifier and create a new row if
     *      doesn't have;
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidArgumentException
     */
    public function save($models, string $mode = self::AUTO)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach ($models as $index => $model) {
            $this->saveModel($model, $mode);
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
     * @param string $mode See the `save` method for details
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidArgumentException
     */
    protected function saveModel(ModelInterface $model, string $mode)
    {
        $identifierField = $model::getIdentifierField();
        $row = $model->convertToRow();
        $doUpdate = false;

        switch ($mode) {
            case static::ADD:
                unset($row[$identifierField]);
                break;
            case static::ADD_AND_KEEP_ID:
                break;
            case static::UPDATE:
                unset($row[$identifierField]);
                $doUpdate = true;
                break;
            case static::AUTO:
                unset($row[$identifierField]);
                $doUpdate = $model->doesExistInDatabase();
                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    'An unexpected $mode value given (%s)',
                    is_string($mode)
                        ? sprintf('"%s"', $mode)
                        : (is_object($mode) ? get_class($mode) : gettype($mode))
                ));
        }

        try {
            if ($doUpdate) {
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
