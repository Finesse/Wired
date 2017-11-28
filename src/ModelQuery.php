<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Query;
use Finesse\MiniDB\QueryProxy;
use Finesse\QueryScribe\Query as OriginalQuery;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\ExceptionInterface;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;

/**
 * Query builder for targeting a model.
 *
 * All the methods throw Finesse\Wired\Exceptions\ExceptionInterface.
 *
 * @author Surgie
 */
class ModelQuery extends QueryProxy
{
    /**
     * @var string|null|ModelInterface The target model class name (actually ModelInterface instance is not allowed, it
     *     is specified here to type-hint IDE)
     */
    protected $modelClass;

    /**
     * {@inheritDoc}
     * @param Query $baseQuery Underlying database query object
     * @param string|null $modelClass Target model class name (already checked)
     */
    public function __construct(Query $baseQuery, string $modelClass = null)
    {
        try {
            parent::__construct($baseQuery);
            $this->modelClass = $modelClass;
        } catch (\Throwable $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * @return string|null|ModelInterface The target model class name (actually ModelInterface instance is not returned,
     *     it is specified here to type-hint IDE)
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @ignore Makes this method be public
     *
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
        try {
            if ($this->modelClass === null) {
                throw new IncorrectQueryException('This query is not a model query');
            }

            $idField = $this->modelClass::getIdentifierField();

            if (is_array($id)) {
                return (clone $this)->whereIn($idField, $id)->get();
            } else {
                return (clone $this)->where($idField, $id)->first();
            }
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Adds a model relation criterion.
     *
     * @param string $relationName Current model relation name. You can specify chained relation by passing multiple
     *     relations names joined with a dot.
     * @param ModelInterface|ModelInterface[]|\Closure|null $target Relation constraint. ModelInterface means "must
     *     be related to the specified model". Models array means "must be related to one of the specified models".
     *     Closure means "must be related to a model that fit the clause in the closure". Null means "must be related to
     *     at least one model".
     * @param bool $not Whether the rule should be "not related"
     * @param string $appendRule How the criterion should be appended to the others (SQL boolean operator name)
     * @return $this
     * @throws RelationException
     * @throws NotModelException
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     * @throws IncorrectQueryException
     * @throws IncorrectModelException
     */
    public function whereRelation(
        string $relationName,
        $target = null,
        bool $not = false,
        string $appendRule = 'AND'
    ): self {
        try {
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
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
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
        try {
            return (new static($this->baseQuery->makeCopyForCriteriaGroup(), $this->modelClass))
                ->applyCallback($callback)
                ->baseQuery;
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    /**
     * Makes an empty model query object which can be used as a subquery for this query. If this query model class and
     * the target model class are the same, the subquery receives an alias for the table.
     *
     * @param string|ModelInterface $modelClass The model class name
     * @return ModelQuery
     * @throws NotModelException
     */
    public function makeModelSubQuery(string $modelClass): self
    {
        try {
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

            return (new static($this->baseQuery->makeCopyForSubQuery(), $modelClass))
                ->table($subQueryTable, $subQueryTableAlias);
        } catch (\Throwable $exception) {
            return $this->handleException($exception);
        }
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
    protected function handleException(\Throwable $exception)
    {
        try {
            return parent::handleException($exception);
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }
}
