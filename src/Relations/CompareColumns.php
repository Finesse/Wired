<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\RelationInterface;

/**
 * A common relation between two models where one model field is compared to the other model field.
 *
 * @author Surgie
 */
abstract class CompareColumns implements RelationInterface
{
    /**
     * @var string|null The current (from a query) model field name
     */
    protected $currentModelField;

    /**
     * @var string Columns compare rule (for the query whereColumn method)
     * @see ModelQuery::where
     */
    protected $compareRule;

    /**
     * @var string|ModelInterface The target model class name
     */
    protected $targetModelClass;

    /**
     * @var string|null The target model field name
     */
    protected $targetModelField;

    /**
     * @param string|null $currentModelField The current (from a query) model field name. If null, the current model
     *     identifier will be used.
     * @param string $compareRule Columns compare rule (for the query whereColumn method)
     * @param string $targetModelClass The target model class name
     * @param string|null $targetModelField The target model field name. If null, the target model identifier will be
     *     used.
     * @throws NotModelException
     */
    public function __construct(
        string $currentModelField = null,
        string $compareRule = '',
        string $targetModelClass,
        string $targetModelField = null
    ) {
        NotModelException::checkModelClass('The target model class name', $targetModelClass);

        $this->currentModelField = $currentModelField;
        $this->compareRule = $compareRule;
        $this->targetModelClass = $targetModelClass;
        $this->targetModelField = $targetModelField;
    }

    /**
     * {@inheritDoc}
     */
    public function applyToQueryWhere(ModelQuery $query, $target)
    {
        if ($target instanceof ModelInterface) {
            return $this->applyToQueryWhereWithModel($query, $target);
        }

        if ($target === null || $target instanceof \Closure) {
            return $this->applyToQueryWhereWithClause($query, $target);
        }

        throw new InvalidArgumentException(sprintf(
            'The relation argument expected to be %s, %s or null, %s given',
            ModelInterface::class,
            \Closure::class,
            is_object($target) ? get_class($target) : gettype($target)
        ));
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
            $this->getCurrentModelField($query),
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
            $query->getTableIdentifier().'.'.$this->getCurrentModelField($query),
            $this->compareRule,
            $subQuery->getTableIdentifier().'.'.$this->getTargetModelField()
        );

        $query->whereExists($subQuery);
    }

    /**
     * Gets the current model field name.
     *
     * @param ModelQuery $query A query with the current model
     * @return string
     */
    protected function getCurrentModelField(ModelQuery $query): string
    {
        return $this->currentModelField ?? $query->modelClass::getIdentifierField();
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
