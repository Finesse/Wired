<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\NotModelException;
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
     * @var bool Does the parent model has 0-many child models (true) or 0-1 (false)
     */
    protected $expectsManyObjectModels;

    /**
     * @param string|null $parentModelField The parent model field name. If null, the parent model identifier will be
     *  used.
     * @param string $childModelClass The child model class name
     * @param string|null $childModelField The child model field name. If null, the child model identifier will be
     *  used.
     * @param bool $expectsManyObjectModels Does the parent model has 0-many child models (true) or 0-1 (false)?
     * @throws NotModelException
     */
    public function __construct(
        string $parentModelField = null,
        string $childModelClass,
        string $childModelField = null,
        bool $expectsManyObjectModels
    ) {
        Helpers::checkModelClass('The child model class name', $childModelClass);

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
        RelationHelpers::addConstraintToQuery(
            $query,
            $this->getParentModelField($query),
            $this->getChildModelField(),
            $this->childModelClass,
            $constraint
        );
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
        $parentModelField = $this->getParentModelField($sampleParent);
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
     * Gets the parent model field name from the relation property, a model instance, a model class name or a query
     * object.
     *
     * @param string|ModelInterface|ModelQuery $hint The model class name or the query object
     * @return string
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     * @throws NotModelException
     */
    protected function getParentModelField($hint): string
    {
        return $this->parentModelField ?? Helpers::getModelIdentifierField($hint);
    }

    /**
     * Gets the object model field name.
     */
    protected function getChildModelField(): string
    {
        return $this->childModelField ?? $this->childModelClass::getIdentifierField();
    }
}
