<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\ModelQuery;
use Finesse\Wired\RelationInterface;

/**
 * Models relation: a subject model belongs to an object model, i.e. the object model has many subject models.
 *
 * @author Surgie
 */
class BelongsTo implements RelationInterface
{
    /**
     * @var string|ModelInterface The object model class name (checked)
     */
    protected $modelClass;

    /**
     * @var string The name of the subject model field which contains an identifier of an object model
     */
    protected $foreignField;

    /**
     * @var string|null The name of the object model field which contains a value which the foreign key targets. Null
     *     means that the default object model identifier field should be used.
     */
    protected $identifierField;

    /**
     * @param string $modelClass The object model class name
     * @param string $foreignField The name of the subject model field which contains an identifier of an object model
     * @param string|null $identifierField The name of the object model field which contains a value which the foreign
     *     key targets. Null means that the default object model identifier field should be used.
     * @throws NotModelException
     */
    public function __construct(string $modelClass, string $foreignField, string $identifierField = null)
    {
        NotModelException::checkModelClass('Argument $modelClass', $modelClass);

        $this->modelClass = $modelClass;
        $this->foreignField = $foreignField;
        $this->identifierField = $identifierField;
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
        if (!$model instanceof $this->modelClass) {
            throw new RelationException(sprintf(
                'The given model %s is not a %s model',
                get_class($model),
                $this->modelClass
            ));
        }

        $query->where(
            $this->foreignField,
            $model->{$this->identifierField ?? $model::getIdentifierField()}
        );
    }

    /**
     * Applies itself with a callback clause to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param \Closure $clause The clause. Null means no clause.
     */
    protected function applyToQueryWhereWithClause(ModelQuery $query, \Closure $clause = null)
    {
        // whereColumn is applied after to make sure that the relation closure is applied the the AND rule
        $subQuery = $query->resolveModelSubQueryClosure($this->modelClass, $clause ?? function () {});
        $subQuery->whereColumn(
            $query->getTableIdentifier().'.'.$this->foreignField,
            $subQuery->getTableIdentifier().'.'.($this->identifierField ?? $this->modelClass::getIdentifierField())
        );

        $query->whereExists($subQuery);
    }
}
