<?php

namespace Finesse\Wired;

use Finesse\MiniDB\Query;
use Finesse\MiniDB\QueryProxy;
use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;
use Finesse\MiniDB\Exceptions\IncorrectQueryException as DBIncorrectQueryException;
use Finesse\MiniDB\Exceptions\InvalidArgumentException as DBInvalidArgumentException;
use Finesse\QueryScribe\Query as QSQuery;
use Finesse\QueryScribe\QueryBricks\Criterion;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\ExceptionInterface;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Relations\BelongsTo;
use Finesse\Wired\Relations\HasMany;

/**
 * Query builder for targeting a model.
 *
 * @author Surgie
 */
class ModelQuery extends QueryProxy
{
    /**
     * @var string|null|ModelInterface Target model class name
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
     * @param string $relationName Current model relation name
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must be related to the
     *     specified model". Closure means "must be related to a model that fit the clause in the closure". Null means
     *     "must be related to anything".
     * @param bool $not Whether the rule should be "not related"
     * @param int $appendRule How the criterion should be appended to the others (on of Criterion::APPEND_RULE_*
     *    constants)
     * @return $this
     * @throws RelationException
     * @throws InvalidArgumentException
     * @throws IncorrectQueryException
     */
    public function whereRelation(
        string $relationName,
        $target = null,
        bool $not = false,
        int $appendRule = Criterion::APPEND_RULE_AND
    ): self {
        if ($this->modelClass === null) {
            throw new IncorrectQueryException('This query is not a model query');
        }

        $relation = $this->modelClass::getRelation($relationName);

        if ($relation === null) {
            throw new RelationException(sprintf(
                'The relation `%s` is not defined in the %s model',
                $relationName,
                $this->modelClass
            ));
        }

        $relation = $this->modelClass::$relationName();

        if ($relation instanceof BelongsTo) {
            return $this->whereBelongsToRelation($relation, $target, $not, $appendRule);
        }
        if ($relation instanceof HasMany) {
            return $this->whereHasManyRelation($relation, $target, $not, $appendRule);
        }

        throw new RelationException(sprintf(
            'The given relation %s is unknown',
            is_object($relation) ? get_class($relation) : '('.gettype($relation).')'
        ));
    }

    /**
     * Adds a model relation criterion with the OR append rule.
     *
     * @see whereRelation For the arguments and exceptions reference
     * @return $this
     */
    public function orWhereRelation(string $relationName, $target = null)
    {
        return $this->whereRelation($relationName, $target, false, Criterion::APPEND_RULE_OR);
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
        return $this->whereRelation($relationName, $target, true, Criterion::APPEND_RULE_OR);
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

    /**
     * Adds a BelongsTo relation clause.
     *
     * @param BelongsTo $relation Relation
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must belong to the specified
     *     model". Closure means "must belong to models that fit the clause in the closure". Null means "must belong to
     *     anything".
     * @param bool $not
     * @param int $appendRule
     * @return $this
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    protected function whereBelongsToRelation(
        BelongsTo $relation,
        $target = null,
        bool $not = false,
        int $appendRule = Criterion::APPEND_RULE_AND
    ): self {
        return $this->whereRelatedModel(
            $relation->foreignField,
            $relation->identifierField ?? $relation->modelClass::getIdentifierField(),
            $relation->modelClass,
            $target,
            $not,
            $appendRule
        );
    }

    /**
     * Adds a HasMany relation clause.
     *
     * @param BelongsTo $relation Relation
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must have the specified
     *     model". Closure means "must have a model that fit the clause in the closure". Null means "must have
     *     anything".
     * @param bool $not
     * @param int $appendRule
     * @return $this
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    protected function whereHasManyRelation(
        HasMany $relation,
        $target = null,
        bool $not = false,
        int $appendRule = Criterion::APPEND_RULE_AND
    ): self {
        return $this->whereRelatedModel(
            $relation->identifierField ?? $relation->modelClass::getIdentifierField(),
            $relation->foreignField,
            $relation->modelClass,
            $target,
            $not,
            $appendRule
        );
    }

    /**
     * Adds a related model clause.
     *
     * @param string $parentField The field name of the current query model
     * @param string $childField The field name of the related model
     * @param string|ModelInterface $childModelClass The related model class name
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must be related to the
     *     specified model". Closure means "must be related to a model that fit the clause in the closure". Null means
     *     "must be related to anything".
     * @param bool $not
     * @param int $appendRule
     * @return $this
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    protected function whereRelatedModel(
        string $parentField,
        string $childField,
        string $childModelClass,
        $child = null,
        bool $not = false,
        int $appendRule = Criterion::APPEND_RULE_AND
    ): self {
        if ($child instanceof ModelInterface) {
            if (!$child instanceof $childModelClass) {
                throw new RelationException(sprintf(
                    'The given model %s is not %s model',
                    get_class($child),
                    $childModelClass
                ));
            }

            return $this->where(
                $parentField,
                $not ? '!=' : '=',
                $child->$childField,
                $appendRule
            );
        }

        if ($child === null || $child instanceof \Closure) {
            return $this->whereExists(function (self $query) use ($parentField, $childField, $childModelClass, $child) {
                $parentTableName = $this->baseQuery->tableAlias ?? $this->baseQuery->table;
                $childTable = $childModelClass::getTable();
                $childTableAlias = null;

                if ($childTable === $parentTableName) {
                    for ($i = 0;; ++$i) {
                        $childTableAlias = '__wired_reserved_alias_'.$i;
                        if ($childTableAlias !== $parentTableName) {
                            break;
                        }
                    }
                }

                $childTableName = $childTableAlias ?? $childTable;

                $query->modelClass = $childModelClass;
                $query->table($childTable, $childTableAlias);
                $query->whereColumn(
                    $parentTableName.'.'.$parentField,
                    $childTableName.'.'.$childField
                );

                return $child ? $child($query) : $query;
            }, $not, $appendRule);
        }

        throw new InvalidArgumentException(sprintf(
            'The second argument expected to be %s, %s or null, %s given',
            ModelInterface::class,
            \Closure::class,
            is_object($child) ? get_class($child) : gettype($child)
        ));
    }
}
