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
 * The Subject and Object terms are used here. Subject is the model which has the relation. Object is the related model.
 *
 * @author Surgie
 */
abstract class EqualFields implements RelationInterface
{
    /**
     * @var string|null The subject model field name
     */
    protected $subjectModelField;

    /**
     * @var string|ModelInterface The object model class name (checked)
     */
    protected $objectModelClass;

    /**
     * @var string|null The object model field name
     */
    protected $objectModelField;

    /**
     * @var bool Does the subject model has 0-many object models (true) or 0-1 (false)
     */
    protected $expectsManyObjectModels;

    /**
     * @param string|null $subjectModelField The subject model field name. If null, the subject model identifier will be
     *     used.
     * @param string $objectModelClass The object model class name
     * @param string|null $objectModelField The object model field name. If null, the object model identifier will be
     *     used.
     * @param bool $expectsManyObjectModels Does the subject model has 0-many object models (true) or 0-1 (false)?
     * @throws NotModelException
     */
    public function __construct(
        string $subjectModelField = null,
        string $objectModelClass,
        string $objectModelField = null,
        bool $expectsManyObjectModels
    ) {
        Helpers::checkModelClass('The object model class name', $objectModelClass);

        $this->subjectModelField = $subjectModelField;
        $this->objectModelClass = $objectModelClass;
        $this->objectModelField = $objectModelField;
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
    public function loadRelatives(Mapper $mapper, string $name, array $models, \Closure $constraint = null)
    {
        if (!$models) {
            return;
        }

        $sampleModel = reset($models);
        $subjectModelField = $this->getSubjectModelField(get_class($sampleModel));
        $objectModelField = $this->getObjectModelField();

        // Collecting the list of object model column values
        $searchValues = Helpers::getObjectsPropertyValues($models, $subjectModelField, true);

        // Getting relative models
        if ($searchValues) {
            $query = $mapper->model($this->objectModelClass);
            if ($constraint) {
                $query = $constraint($query) ?? $query;
            }
            // whereIn is applied after to make sure that the relation closure is applied with the AND rule
            $relatives = $query->whereIn($this->getObjectModelField(), $searchValues)->get();
        } else {
            $relatives = [];
        }

        // Setting the relative models to the input models
        if ($this->expectsManyObjectModels) {
            $groupedRelatives = Helpers::groupObjectsByProperty($relatives, $objectModelField);
            foreach ($models as $model) {
                $model->setLoadedRelatives($name, $groupedRelatives[$model->$subjectModelField] ?? []);
            }
        } else {
            $relatives = Helpers::indexObjectsByProperty($relatives, $objectModelField);
            foreach ($models as $model) {
                $model->setLoadedRelatives($name, $relatives[$model->$subjectModelField] ?? null);
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
        $this->checkObjectModel($model);

        $query->where(
            $this->getSubjectModelFieldFromQuery($query),
            '=',
            $model->{$this->getObjectModelField()}
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
            $this->checkObjectModel($model);
        }

        $searchValues = Helpers::getObjectsPropertyValues($models, $this->getObjectModelField(), true);

        if ($searchValues) {
            $query->whereIn($this->getSubjectModelFieldFromQuery($query), $searchValues);
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
        // whereColumn is applied after to make sure that the relation closure is applied with the AND rule
        $subQuery = $query->resolveModelSubQueryClosure($this->objectModelClass, $clause ?? function () {});
        $subQuery->whereColumn(
            $query->getTableIdentifier().'.'.$this->getSubjectModelFieldFromQuery($query),
            '=',
            $subQuery->getTableIdentifier().'.'.$this->getObjectModelField()
        );

        $query->whereExists($subQuery);
    }

    /**
     * Gets the subject model field name.
     *
     * @param string|ModelInterface $subjectModelClass Subject model class name
     * @return string
     */
    protected function getSubjectModelField(string $subjectModelClass): string
    {
        return $this->subjectModelField ?? $subjectModelClass::getIdentifierField();
    }

    /**
     * Gets the subject model field name. Uses a query object to get the subject model class name.
     *
     * @param ModelQuery $query
     * @return string
     * @throws IncorrectQueryException If the query doesn't have a model
     */
    protected function getSubjectModelFieldFromQuery(ModelQuery $query): string
    {
        $modelClass = $query->getModelClass();
        if ($modelClass === null) {
            throw new IncorrectQueryException('The given query doesn\'t have a context model');
        }

        return $this->getSubjectModelField($modelClass);
    }

    /**
     * Gets the object model field name.
     *
     * @return string
     */
    protected function getObjectModelField(): string
    {
        return $this->objectModelField ?? $this->objectModelClass::getIdentifierField();
    }

    /**
     * Checks that the given value is an object model class model.
     *
     * @param ModelInterface|mixed $model
     * @throws NotModelException If the given value is not a model
     * @throws RelationException If the model is not an object model class model
     */
    protected function checkObjectModel($model)
    {
        if ($model instanceof $this->objectModelClass) {
            return;
        }

        if ($model instanceof ModelInterface) {
            throw new RelationException(sprintf(
                'The given model %s is not a %s model',
                get_class($model),
                $this->objectModelClass
            ));
        }

        throw new NotModelException(sprintf(
            'The given value (%s) is not a model',
            is_object($model) ? get_class($model) : gettype($model)
        ));
    }
}
