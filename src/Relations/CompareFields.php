<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\RelationInterface;

/**
 * A common relation between two models where one model field is compared to the other model field.
 *
 * @author Surgie
 */
abstract class CompareFields implements RelationInterface
{
    /**
     * @var string|null The current model field name
     */
    protected $currentModelField;

    /**
     * @var string Columns compare rule (for the query whereColumn method)
     * @see ModelQuery::where
     */
    protected $compareRule;

    /**
     * @var string|ModelInterface The target model class name (checked)
     */
    protected $targetModelClass;

    /**
     * @var string|null The target model field name
     */
    protected $targetModelField;

    /**
     * @var bool
     */
    protected $expectsManyTargetModels;

    /**
     * @param string|null $currentModelField The current model field name. If null, the current model identifier will be
     *     used.
     * @param string $compareRule Columns compare rule (for the query whereColumn method)
     * @param string $targetModelClass The target model class name
     * @param string|null $targetModelField The target model field name. If null, the target model identifier will be
     *     used.
     * @param bool $expectsManyTargetModels Does the current model has 0-many target models (true) or 0-1 (false)?
     * @throws NotModelException
     */
    public function __construct(
        string $currentModelField = null,
        string $compareRule = '',
        string $targetModelClass,
        string $targetModelField = null,
        bool $expectsManyTargetModels
    ) {
        Helpers::checkModelClass('The target model class name', $targetModelClass);

        $this->currentModelField = $currentModelField;
        $this->compareRule = $compareRule;
        $this->targetModelClass = $targetModelClass;
        $this->targetModelField = $targetModelField;
        $this->expectsManyTargetModels = $expectsManyTargetModels;
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

        throw new InvalidArgumentException(sprintf(
            'The constraint argument expected to be %s, %s or null, %s given',
            ModelInterface::class,
            \Closure::class,
            is_object($constraint) ? get_class($constraint) : gettype($constraint)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function loadRelatives(
        Mapper $mapper,
        string $name,
        array $models,
        \Closure $constraint = null,
        bool $onlyMissing = false
    ) {
        // Discarding the models that already have loaded relatives
        if ($onlyMissing) {
            $models = array_filter($models, function (ModelInterface $model) use ($name) {
                return !$model->doesHaveLoadedRelatives($name);
            });
        }

        if (empty($models)) {
            return;
        }

        $sampleModel = reset($models);
        $currentModelField = $this->getCurrentModelField(get_class($sampleModel));
        $targetModelField = $this->getTargetModelField();

        // Collecting the list of unique target model column values
        $searchValues = [];
        foreach ($models as $model) {
            $searchValue = $model->$currentModelField;

            if ($searchValue === null) {
                continue;
            }
            if (!is_scalar($searchValue)) {
                throw new IncorrectModelException(sprintf(
                    'The model `%s` field value expected to be scalar or null, %s given',
                    $currentModelField,
                    is_object($searchValue) ? get_class($searchValue) : gettype($searchValue)
                ));
            }

            $searchValues[$searchValue] = true;
        }
        $searchValues = array_keys($searchValues);

        // Getting relative model
        if (!empty($searchValues)) {
            $query = $mapper->model($this->targetModelClass);
            if ($constraint) {
                $query = $constraint($query) ?? $query;
            }
            // whereIn is applied after to make sure that the relation closure is applied with the AND rule
            $relatives = $query->whereIn($this->getTargetModelField(), $searchValues)->get();
        } else {
            $relatives = [];
        }

        // Setting the relative models to the input models
        if ($this->expectsManyTargetModels) {
            $groupedRelatives = Helpers::groupObjectsByProperty($relatives, $targetModelField);
            foreach ($models as $model) {
                $model->setLoadedRelatives($name, $groupedRelatives[$model->$currentModelField] ?? []);
            }
        } else {
            $relatives = Helpers::indexObjectsByProperty($relatives, $targetModelField);
            foreach ($models as $model) {
                $model->setLoadedRelatives($name, $relatives[$model->$currentModelField] ?? null);
            }
        }
    }

    /**
     * Applies itself with a model object to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param ModelInterface $model The model
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    protected function applyToQueryWhereWithModel(ModelQuery $query, ModelInterface $model)
    {
        if (!$model instanceof $this->targetModelClass) {
            throw new RelationException(sprintf(
                'The given model %s is not a %s model',
                get_class($model),
                $this->targetModelClass
            ));
        }

        $query->where(
            $this->getCurrentModelField($query->modelClass),
            $this->compareRule,
            $model->{$this->getTargetModelField()}
        );
    }

    /**
     * Applies itself with a callback clause to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param \Closure $clause The clause. Null means no clause.
     * @throws NotModelException
     * @throws InvalidArgumentException
     */
    protected function applyToQueryWhereWithClause(ModelQuery $query, \Closure $clause = null)
    {
        // whereColumn is applied after to make sure that the relation closure is applied with the AND rule
        $subQuery = $query->resolveModelSubQueryClosure($this->targetModelClass, $clause ?? function () {});
        $subQuery->whereColumn(
            $query->getTableIdentifier().'.'.$this->getCurrentModelField($query->modelClass),
            $this->compareRule,
            $subQuery->getTableIdentifier().'.'.$this->getTargetModelField()
        );

        $query->whereExists($subQuery);
    }

    /**
     * Gets the current model field name.
     *
     * @param string|ModelInterface $currentModelClass Current model class name
     * @return string
     */
    protected function getCurrentModelField(string $currentModelClass): string
    {
        return $this->currentModelField ?? $currentModelClass::getIdentifierField();
    }

    /**
     * Gets the target model field name.
     *
     * @return string
     */
    protected function getTargetModelField(): string
    {
        return $this->targetModelField ?? $this->targetModelClass::getIdentifierField();
    }
}
