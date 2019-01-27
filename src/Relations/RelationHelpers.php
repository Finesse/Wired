<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Helpers;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\ModelQuery;

/**
 * Contains functions to help implement relations and only relations
 *
 * @author Surgie
 */
class RelationHelpers
{
    /**
     * Applies a relation constraint to the "where" part of a query
     *
     * @param ModelQuery $query The query
     * @param string $parentField The parent model field name
     * @param string $childField The child model field name
     * @param string $childClass The child model class name
     * @param ModelInterface|ModelInterface[]|\Closure|null $constraint (see RelationInterface::applyToQueryWhere)
     * @throws InvalidArgumentException
     * @throws IncorrectModelException
     * @throws IncorrectQueryException
     * @throws NotModelException
     */
    public static function addConstraintToQuery(
        ModelQuery $query,
        string $parentField,
        string $childField,
        string $childClass,
        $constraint
    ) {
        if ($constraint instanceof ModelInterface) {
            static::addModelConstraintToQuery($query, $parentField, $childField, $childClass, $constraint);
            return;
        }

        if ($constraint === null || $constraint instanceof \Closure) {
            static::addClauseConstraintToQuery($query, $parentField, $childField, $childClass, $constraint);
            return;
        }

        if (is_array($constraint)) {
            static::addModelsConstraintToQuery($query, $parentField, $childField, $childClass, $constraint);
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The constraint argument expected to be %s, %s[], %s or null, %s given',
            ModelInterface::class,
            ModelInterface::class,
            \Closure::class,
            is_object($constraint) ? get_class($constraint) : gettype($constraint)
        ));
    }

    /**
     * Applies a single model constraint to a query
     *
     * @param ModelInterface $model The model
     * @throws IncorrectModelException
     * @throws IncorrectQueryException
     */
    protected static function addModelConstraintToQuery(
        ModelQuery $query,
        string $parentField,
        string $childField,
        string $childClass,
        ModelInterface $model
    ) {
        Helpers::checkModelObjectClass($model, $childClass);
        $query->where($parentField, $model->$childField);
    }

    /**
     * Applies a "related to any of the given models" constraint to a query
     *
     * @param ModelInterface[] $models The models list
     * @throws NotModelException
     * @throws IncorrectModelException
     * @throws IncorrectQueryException
     */
    protected static function addModelsConstraintToQuery(
        ModelQuery $query,
        string $parentField,
        string $childField,
        string $childClass,
        array $models
    ) {
        foreach ($models as $model) {
            Helpers::checkModelObjectClass($model, $childClass);
        }

        $searchValues = Helpers::getObjectsPropertyValues($models, $childField, true);
        $query->whereIn($parentField, $searchValues);
    }

    /**
     * Applies a clause constraint to a query
     *
     * @param \Closure $clause The clause. Null means no clause.
     * @throws NotModelException
     * @throws IncorrectQueryException
     */
    protected static function addClauseConstraintToQuery(
        ModelQuery $query,
        string $parentField,
        string $childField,
        string $childClass,
        \Closure $clause = null
    ) {
        $subQuery = $query->makeModelSubQuery($childClass);
        if ($clause) {
            $subQuery = $subQuery->apply($clause);
        }

        // whereColumn is applied after to make sure that the relation closure is applied with the AND rule
        $subQuery->whereColumn(
            $query->getTableIdentifier().'.'.$parentField,
            $subQuery->getTableIdentifier().'.'.$childField
        );

        $query->whereExists($subQuery->getBaseQuery());
    }
}
