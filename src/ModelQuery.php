<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Query;
use Finesse\MiniDB\QueryProxy;
use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\QueryScribe\Query as OriginalQuery;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\ExceptionInterface;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;

/**
 * Query builder for targeting a model.
 *
 * All the methods throw Finesse\Wired\Exceptions\InvalidArgumentException exceptions if not specified explicitly.
 *
 * @author Surgie
 */
class ModelQuery extends QueryProxy
{
    /**
     * @var string|null|ModelInterface Target model class name (actually ModelInterface instance is not allowed, it
     *     is specified here to type-hint IDE)
     */
    public $modelClass;

    /**
     * {@inheritDoc}
     * @param Query $baseQuery Underlying database query object
     * @param string|null $modelClass Target model class name (already checked)
     */
    public function __construct(Query $baseQuery, string $modelClass = null)
    {
        parent::__construct($baseQuery);
        $this->modelClass = $modelClass;
    }

    /**
     * {@inheritDoc}
     * @return Query
     */
    public function getBaseQuery(): OriginalQuery
    {
        return parent::getBaseQuery();
    }

    /**
     * Gets the model by the identifier.
     *
     * @param int|string|int[]|string[] $id Identifier or an array of identifiers
     * @return ModelInterface|null|ModelInterface[] If an identifier is given, a model object or null is returned. If an
     *     array of identifiers is given, an array of models is returned (order is not defined).
     * @throws InvalidArgumentException
     * @throws IncorrectQueryException
     * @throws DatabaseException
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
     * Adds a model relation criterion.
     *
     * @param string $relationName Current model relation name. You can specify chained relation by passing multiple
     *     relations names joined with a dot.
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must be related to the
     *     specified model". Closure means "must be related to a model that fit the clause in the closure". Null means
     *     "must be related to at least one model".
     * @param bool $not Whether the rule should be "not related"
     * @param string $appendRule How the criterion should be appended to the others (SQL boolean operator name)
     * @return $this
     * @throws RelationException
     * @throws InvalidArgumentException
     * @throws IncorrectQueryException
     * @throws IncorrectModelException
     */
    public function whereRelation(
        string $relationName,
        $target = null,
        bool $not = false,
        string $appendRule = 'AND'
    ): self {
        if ($this->modelClass === null) {
            throw new IncorrectQueryException('This query is not a model query');
        }

        // Resolve the chained relations
        $relationsChain = explode('.', $relationName, 2);
        if (count($relationsChain) > 1) {
            $relationName = $relationsChain[0];
            $target = function (self $query) use ($relationsChain, $target) {
                $query->whereRelation($relationsChain[1], $target);
            };
        }

        // Get the relation object
        $relation = $this->modelClass::getRelationOrFail($relationName);

        // Add the relation criterion
        $applyRelation = function (self $query) use ($relation, $target) {
            $relation->applyToQueryWhere($query, $target);
        };
        if ($not) {
            return $this->whereNot($applyRelation, $appendRule);
        } else {
            return $this->where($applyRelation, null, null, $appendRule);
        }
    }

    /**
     * Adds a model relation criterion with the OR append rule.
     *
     * @see whereRelation For the arguments and exceptions reference
     * @return $this
     */
    public function orWhereRelation(string $relationName, $target = null)
    {
        return $this->whereRelation($relationName, $target, false, 'OR');
    }

    /**
     * Adds a model relation absence criterion.
     *
     * @see whereRelation For the arguments and exceptions reference
     * @return $this
     */
    public function whereNoRelation(string $relationName, $target = null)
    {
        return $this->whereRelation($relationName, $target, true);
    }

    /**
     * Adds a model relation absence criterion with the OR append rule.
     *
     * @see whereRelation For the arguments and exceptions reference
     * @return $this
     */
    public function orWhereNoRelation(string $relationName, $target = null)
    {
        return $this->whereRelation($relationName, $target, true, 'OR');
    }

    /**
     * {@inheritDoc}
     * @return Query
     */
    public function resolveCriteriaGroupClosure(\Closure $callback): OriginalQuery
    {
        $query = new static($this->baseQuery->makeCopyForCriteriaGroup(), $this->modelClass);
        return $this->resolveClosure($callback, $query);
    }

    /**
     * Resolves a closure given instead of a subquery. Gives a query with applied model table (with prevented table
     * names conflicts) to the first argument of the callback.
     *
     * @param string|ModelInterface $modelClass The model class name
     * @param \Closure $callback
     * @return Query
     * @throws NotModelException
     */
    public function resolveModelSubQueryClosure(string $modelClass, \Closure $callback): OriginalQuery
    {
        Helpers::checkModelClass('The given model class', $modelClass);

        $queryTableName = $this->baseQuery->getTableIdentifier();
        $subQueryTable = $modelClass::getTable();
        $subQueryTableAlias = null;

        if ($subQueryTable === $queryTableName) {
            $counter = 0;
            do {
                $subQueryTableAlias = '__wired_reserved_alias_'.$counter++;
            } while ($subQueryTableAlias === $queryTableName);
        }

        return $this->resolveSubQueryClosure(function (
            self $subQuery
        ) use (
            $modelClass,
            $subQueryTable,
            $subQueryTableAlias,
            $callback
        ) {
            $subQuery->modelClass = $modelClass;
            $subQuery->table($subQueryTable, $subQueryTableAlias);
            return $callback($subQuery);
        });
    }

    /**
     * {@inheritDoc}
     * @return ModelInterface|mixed
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
        if ($exception instanceof DBInvalidArgumentException) {
            throw new InvalidArgumentException($exception->getMessage(), $exception->getCode(), $exception);
        }
        if ($exception instanceof DBIncorrectQueryException) {
            throw new IncorrectQueryException($exception->getMessage(), $exception->getCode(), $exception);
        }
        if ($exception instanceof DBDatabaseException) {
            throw new DatabaseException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return parent::handleBaseQueryException($exception);
    }
}
