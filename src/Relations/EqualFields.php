<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\RelationInterface;

/**
 * A common relation between two models where one model field value is equal to the other model field value.
 *
 * @author Surgie
 */
abstract class EqualFields implements RelationInterface
{
    /**
     * @var string|null The parent model field name
     */
    protected $parentModelField;

    /**
     * @var string|ModelInterface The child model class name (checked)
     */
    protected $childModelClass;

    /**
     * @var string|null The child model field name
     */
    protected $childModelField;

    /**
     * @var bool Does the parent model has 0-many object models (true) or 0-1 (false)
     */
    protected $expectsManyObjectModels;

    /**
     * @param string|null $parentModelField The subject model field name. If null, the subject model identifier will be
     *  used.
     * @param string $childModelClass The object model class name
     * @param string|null $childModelField The object model field name. If null, the object model identifier will be
     *  used.
     * @param bool $expectsManyObjectModels Does the parent model has 0-many object models (true) or 0-1 (false)?
     * @throws NotModelException
     */
    public function __construct(
        string $parentModelField = null,
        string $childModelClass,
        string $childModelField = null,
        bool $expectsManyObjectModels
    ) {
        Helpers::checkModelClass('The object model class name', $childModelClass);

        $this->parentModelField = $parentModelField;
        $this->childModelClass = $childModelClass;
        $this->childModelField = $childModelField;
        $this->expectsManyObjectModels = $expectsManyObjectModels;
    }

    /**
     * {@inheritDoc}
     */
    public function applyToQueryWhere(ModelQuery $query, $constraint = null)
    {
        if ($constraint instanceof ModelInterface) {
            return $this->applyToQueryWhereWithModel($query, $constraint);
        }

        if ($constraint === null || $constraint instanceof \Closure) {
            return $this->applyToQueryWhereWithClause($query, $constraint);
        }

        if (is_array($constraint)) {
            return $this->applyToQueryWhereWithModels($query, $constraint);
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
     * {@inheritDoc}
     */
    public function loadRelatives(Mapper $mapper, string $name, array $parents, \Closure $constraint = null)
    {
        if (!$parents) {
            return;
        }

        $sampleParent = reset($parents);
        $parentModelField = $this->getParentModelField(get_class($sampleParent));
        $childModelField = $this->getChildModelField();

        // Collecting the list of child model column values
        $searchValues = Helpers::getObjectsPropertyValues($parents, $parentModelField, true);

        // Getting child models
        if ($searchValues) {
            $query = $mapper->model($this->childModelClass);
            if ($constraint) {
                $query = $query->apply($constraint);
            }
            // whereIn is applied after to make sure that the relation closure is applied with the AND rule
            $children = $query->whereIn($this->getChildModelField(), $searchValues)->get();
        } else {
            $children = [];
        }

        // Setting the child models to the parent models
        if ($this->expectsManyObjectModels) {
            $groupedChildren = Helpers::groupObjectsByProperty($children, $childModelField);
            foreach ($parents as $model) {
                $model->setLoadedRelatives($name, $groupedChildren[$model->$parentModelField] ?? []);
            }
        } else {
            $children = Helpers::indexObjectsByProperty($children, $childModelField);
            foreach ($parents as $model) {
                $model->setLoadedRelatives($name, $children[$model->$parentModelField] ?? null);
            }
        }
    }

    /**
     * Applies itself with a model object to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param ModelInterface $model The model
     * @throws RelationException
     * @throws IncorrectQueryException
     */
    protected function applyToQueryWhereWithModel(ModelQuery $query, ModelInterface $model)
    {
        $this->checkChildModel($model);

        $query->where(
            $this->getParentModelFieldFromQuery($query),
            '=',
            $model->{$this->getChildModelField()}
        );
    }

    /**
     * Applies itself with a models list to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param ModelInterface[] $models The models list
     * @throws NotModelException
     * @throws RelationException
     * @throws IncorrectQueryException
     */
    protected function applyToQueryWhereWithModels(ModelQuery $query, array $models)
    {
        foreach ($models as $model) {
            $this->checkChildModel($model);
        }

        $searchValues = Helpers::getObjectsPropertyValues($models, $this->getChildModelField(), true);

        if ($searchValues) {
            $query->whereIn($this->getParentModelFieldFromQuery($query), $searchValues);
        } else {
            // If the relatives list is empty, the where criterion is equivalent to false
            $query->whereRaw('0');
        }
    }

    /**
     * Applies itself with a callback clause to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param \Closure $clause The clause. Null means no clause.
     * @throws NotModelException
     * @throws IncorrectQueryException
     */
    protected function applyToQueryWhereWithClause(ModelQuery $query, \Closure $clause = null)
    {
        $subQuery = $query->makeModelSubQuery($this->childModelClass);
        if ($clause) {
            $subQuery = $subQuery->apply($clause);
        }

        // whereColumn is applied after to make sure that the relation closure is applied with the AND rule
        $subQuery->whereColumn(
            $query->getTableIdentifier().'.'.$this->getParentModelFieldFromQuery($query),
            '=',
            $subQuery->getTableIdentifier().'.'.$this->getChildModelField()
        );

        $query->whereExists($subQuery->getBaseQuery());
    }

    /**
     * Gets the parent model field name.
     *
     * @param string|ModelInterface $parentModelClass Subject model class name
     * @return string
     */
    protected function getParentModelField(string $parentModelClass): string
    {
        return $this->parentModelField ?? $parentModelClass::getIdentifierField();
    }

    /**
     * Gets the parent model field name. Uses a query object to get the parent model class name.
     *
     * @param ModelQuery $query
     * @return string
     * @throws IncorrectQueryException If the query doesn't have a model
     */
    protected function getParentModelFieldFromQuery(ModelQuery $query): string
    {
        $modelClass = $query->getModelClass();
        if ($modelClass === null) {
            throw new IncorrectQueryException('The given query doesn\'t have a context model');
        }

        return $this->getParentModelField($modelClass);
    }

    /**
     * Gets the object model field name.
     *
     * @return string
     */
    protected function getChildModelField(): string
    {
        return $this->childModelField ?? $this->childModelClass::getIdentifierField();
    }

    /**
     * Checks that the given value is a child model class model.
     *
     * @param ModelInterface|mixed $model
     * @throws NotModelException If the given value is not a model
     * @throws RelationException If the model is not an object model class model
     */
    protected function checkChildModel($model)
    {
        if ($model instanceof $this->childModelClass) {
            return;
        }

        if ($model instanceof ModelInterface) {
            throw new RelationException(sprintf(
                'The given model %s is not a %s model',
                get_class($model),
                $this->childModelClass
            ));
        }

        throw new NotModelException(sprintf(
            'The given value (%s) is not a model',
            is_object($model) ? get_class($model) : gettype($model)
        ));
    }
}
