<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Query;
use Finesse\MiniDB\QueryProxy;
use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\QueryScribe\Exceptions\InvalidArgumentException as QSInvalidArgumentException;
use Finesse\QueryScribe\Query as QSQuery;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\ExceptionInterface;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;

/**
 * Query builder for targeting a model.
 *
 * @todo Fix callback resolving
 *
 * @author Surgie
 */
class ModelQuery extends QueryProxy
{
    /**
     * @var string|null|Model Target model class name
     */
    protected $modelClass;

    /**
     * {@inheritDoc}
     * @param Query $baseQuery Underlying database query object
     * @param string|null $modelClass Target model class name (already checked)
     */
    public function __construct(Query $baseQuery, string $modelClass = null)
    {
        $this->modelClass = $modelClass;
        parent::__construct($baseQuery);
    }

    /**
     * {@inheritDoc}
     * @return Query
     */
    public function getBaseQuery(): QSQuery
    {
        return parent::getBaseQuery();
    }

    /**
     * Gets the model by the identifier.
     *
     * @param int|string|int[]|string[] $id Identifier or an array of identifiers
     * @return Model|null|Model[] If an identifier is given, a model object or null is returned. If an array of
     *     identifiers is given, an array of models is returned (order is not defined).
     */
    public function find($id)
    {
        if ($this->modelClass === null) {
            throw new IncorrectQueryException('This query is not a model query');
        }

        $idField = $this->modelClass::getIdentifierField();

        if (is_array($id)) {
            return (clone $this)->whereIn($idField, $id)->get();
        } else {
            return (clone $this)->where($idField, $id)->first();
        }
    }

    /**
     * {@inheritDoc}
     * @return Model|mixed
     */
    protected function processFetchedRow(array $row)
    {
        if ($this->modelClass !== null) {
            return $this->modelClass::createFromRow($row);
        }

        return parent::processFetchedRow($row);
    }

    /**
     * {@inheritdoc}
     * @throws ExceptionInterface|\Throwable
     */
    protected function handleBaseQueryException(\Throwable $exception)
    {
        if ($exception instanceof DBInvalidArgumentException || $exception instanceof QSInvalidArgumentException) {
            throw new InvalidArgumentException($exception->getMessage(), $exception->getCode(), $exception);
        }

        if ($exception instanceof DBIncorrectQueryException) {
            throw new IncorrectQueryException($exception->getMessage(), $exception->getCode(), $exception);
        }

        if ($exception instanceof DBDatabaseException) {
            throw new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
        }

        parent::handleBaseQueryException($exception);
    }

    /**
     * {@inheritDoc}
     * @return static
     */
    public function resolveCriteriaGroupClosure(\Closure $callback): QSQuery
    {
        $query = new static($this->baseQuery->makeCopyForCriteriaGroup(), $this->modelClass);
        return $this->resolveClosure($callback, $query);
    }
}
