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
 * Models relation: a parent model has many child models and the child model has many parent models.
 *
 * @author Surgie
 */
class BelongsToMany implements RelationInterface, AttachableRelationInterface
{
    /**
     * @var string|null The parent model identifier field name
     */
    protected $parentIdentifierField;

    /**
     * @var string The pivot table field containing parent model identifiers
     */
    protected $pivotParentField;

    /**
     * @var string Pivot table name
     */
    protected $pivotTable;

    /**
     * @var string The pivot table field containing child model identifiers
     */
    protected $pivotChildField;

    /**
     * @var string|null The child model identifier field name
     */
    protected $childIdentifierField;

    /**
     * @var string|ModelInterface The child model class name (checked)
     */
    protected $childModelClass;

    /**
     * @param string $modelClass The child model class name
     * @param string $pivotParentField The pivot table field containing parent model identifiers
     * @param string $pivotTable Pivot table name (a table containing the relation connections)
     * @param string $pivotChildField The pivot table field containing child model identifiers
     * @param string|null $parentIdentifierField The parent model identifier field name. Null means that the default
     *  parent model identifier field should be used.
     * @param string|null $childIdentifierField The child model identifier field name. Null means that the default
     *  child model identifier field should be used.
     */
    public function __construct(
        string $modelClass,
        string $pivotParentField,
        string $pivotTable,
        string $pivotChildField,
        string $parentIdentifierField = null,
        string $childIdentifierField = null
    ) {
        Helpers::checkModelClass('The child model class name', $modelClass);

        $this->parentIdentifierField = $parentIdentifierField;
        $this->pivotParentField = $pivotParentField;
        $this->pivotTable = $pivotTable;
        $this->pivotChildField = $pivotChildField;
        $this->childIdentifierField = $childIdentifierField;
        $this->childModelClass = $modelClass;
    }

    /**
     * {@inheritDoc}
     */
    public function applyToQueryWhere(ModelQuery $query, $constraint = null)
    {
        $pivotQuery = $query->makeSubQuery($this->pivotTable);
        $pivotQuery->whereColumn(
            $query->getTableIdentifier().'.'.$this->getParentModelIdentifierField($query),
            $pivotQuery->getTableIdentifier().'.'.$this->pivotParentField
        );
        RelationHelpers::addConstraintToQuery(
            $pivotQuery,
            $this->pivotChildField,
            $this->getChildModelIdentifierField(),
            $this->childModelClass,
            $constraint
        );
        $query->whereExists($pivotQuery->getBaseQuery());
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
        $parentModelIdentifierField = $this->getParentModelIdentifierField($sampleParent);
        $pivotParentIdentifierAlias = '__wired_reserved_parent_model_id';
        $pivotChildIdentifierAlias = '__wired_reserved_child_model_id';

        // Collecting the list of the parent model identifiers
        $parentIds = Helpers::getObjectsPropertyValues($parents, $parentModelIdentifierField, true);

        // Getting child models
        if ($parentIds) {
            $query = $mapper->model($this->childModelClass);
            $pivotTableAlias = $query->makeSubQueryAliasIfRequired($this->pivotTable);
            $pivotTableIdentifier = $pivotTableAlias ?? $this->pivotTable;

            if ($constraint) {
                $query = $query->apply($constraint)->addTablesToColumnNames();
            }
            if (!$query->getBaseQuery()->select) {
                $query->addSelect($query->getTableIdentifier().'.*');
            }
            $children = $query
                ->addSelect($pivotTableIdentifier.'.'.$this->pivotParentField, $pivotParentIdentifierAlias)
                ->addSelect($pivotTableIdentifier.'.'.$this->pivotChildField, $pivotChildIdentifierAlias)
                ->innerJoin(
                    [$this->pivotTable, $pivotTableAlias],
                    $pivotTableIdentifier.'.'.$this->pivotChildField,
                    $query->getTableIdentifier().'.'.$this->getChildModelIdentifierField()
                )
                ->whereIn($pivotTableIdentifier.'.'.$this->pivotParentField, $parentIds)
                ->getBaseQuery()->get();
        } else {
            $children = [];
        }

        // Setting the relative models to the input models
        $childrenIndexedById = [];
        $groupedChildren = Helpers::groupArraysByKey($children, $pivotParentIdentifierAlias);
        foreach ($parents as $model) {
            $parentChildren = [];
            foreach ($groupedChildren[$model->$parentModelIdentifierField] ?? [] as $row) {
                $childId = $row[$pivotChildIdentifierAlias];

                if (!isset($childrenIndexedById[$childId])) {
                    unset($row[$pivotParentIdentifierAlias]);
                    unset($row[$pivotChildIdentifierAlias]);
                    $childrenIndexedById[$childId] = $this->childModelClass::createFromRow($row);
                }

                $parentChildren[] = $childrenIndexedById[$childId];
            }

            $model->setLoadedRelatives($name, $parentChildren);
        }
    }

    /**
     * @inheritDoc
     *
     * The models additional data are additional fields for the pivot table records (keys are field names)
     *
     * @todo Test
     */
    public function attach(Mapper $mapper, array $parentModels, array $childModels, string $onMatch, bool $detachOther)
    {
        // TODO: Implement attach() method.
    }

    /**
     * @inheritDoc
     */
    public function detach(Mapper $mapper, array $parents, array $children)
    {
        if (!$parents || !$children) {
            return;
        }

        $sampleParent = reset($parents);
        $sampleChild = reset($children);
        Helpers::checkModelObjectClass($sampleChild, $this->childModelClass);
        $parentIdentifiers = Helpers::getObjectsPropertyValues($parents, $this->getParentModelIdentifierField($sampleParent), true);
        $childIdentifiers = Helpers::getObjectsPropertyValues($children, $this->getChildModelIdentifierField(), true);

        try {
            $mapper->getDatabase()
                ->table($this->pivotTable)
                ->whereIn($this->pivotParentField, $parentIdentifiers)
                ->whereIn($this->pivotChildField, $childIdentifiers)
                ->delete();
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }

    /**
     * Gets the parent model identifier field name from the relation property, a model instance, a model class name or a
     * query object.
     *
     * @param string|ModelInterface|ModelQuery $hint The model class name or the query object
     * @return string
     * @throws IncorrectQueryException
     * @throws InvalidArgumentException
     * @throws NotModelException
     */
    protected function getParentModelIdentifierField($hint): string
    {
        return $this->parentIdentifierField ?? Helpers::getModelIdentifierField($hint);
    }

    /**
     * Gets the object model identifier field name.
     */
    protected function getChildModelIdentifierField(): string
    {
        return $this->childIdentifierField ?? $this->childModelClass::getIdentifierField();
    }
}
