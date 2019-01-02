<?php

namespace Finesse\Wired\Relations;

use Finesse\MiniDB\Database;
use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
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
     * The attachments additional data are extra fields for the pivot table records (keys are field names)
     *
     * @todo Test
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     */
    public function attach(
        Mapper $mapper,
        array $parents,
        array $children,
        string $onMatch,
        bool $detachOther,
        callable $getAttachmentData = null
    ) {
        if (!$parents || !$children) {
            return;
        }

        $database = $mapper->getDatabase();
        $sampleParent = reset($parents);
        $sampleChild = reset($children);
        Helpers::checkModelObjectClass($sampleChild, $this->childModelClass);
        $parentIdentifiers = Helpers::getObjectsPropertyValues($parents, $this->getParentModelIdentifierField($sampleParent), true);
        $childIdentifiers = Helpers::getObjectsPropertyValues($children, $this->getChildModelIdentifierField(), true);

        try {
            // Detaching other children (if required)
            if ($detachOther) {
                $this->detachByIdentifiers($database, $parentIdentifiers, $childIdentifiers, true);
            }

            // Attaching the given children
            switch ($onMatch) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case Mapper::REPLACE:
                    $this->detachByIdentifiers($database, $parentIdentifiers, $childIdentifiers);
                    // intentional break skip

                case Mapper::DUPLICATE:
                    $newAttachments = [];

                    foreach ($parentIdentifiers as $parentKey => $parentId) {
                        foreach ($childIdentifiers as $childKey => $childId) {
                            $extraFields = $this->makeAttachmentExtraFields(
                                $parents,
                                $children,
                                $parentKey,
                                $childKey,
                                $getAttachmentData,
                                '$getAttachmentData'
                            );
                            $newAttachments[] = $this->makeAttachmentRow($parentId, $childId, $extraFields);
                        }
                    }

                    if ($newAttachments) {
                        $database->table($this->pivotTable)->insert($newAttachments);
                    }
                    break;

                case Mapper::UPDATE:
                    // todo: Implement

                default:
                    throw new InvalidArgumentException(sprintf(
                        'An unexpected $onMatch value given (%s)',
                        is_string($onMatch)
                            ? sprintf('"%s"', $onMatch)
                            : (is_object($onMatch) ? get_class($onMatch) : gettype($onMatch))
                    ));
            }
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function detach(Mapper $mapper, array $parents, array $children = null)
    {
        if (!$parents || $children !== null && !$children) {
            return;
        }

        $sampleParent = reset($parents);
        $parentIdentifiers = Helpers::getObjectsPropertyValues($parents, $this->getParentModelIdentifierField($sampleParent), true);

        if ($children === null) {
            $childIdentifiers = null;
        } else {
            $sampleChild = reset($children);
            Helpers::checkModelObjectClass($sampleChild, $this->childModelClass);
            $childIdentifiers = Helpers::getObjectsPropertyValues($children, $this->getChildModelIdentifierField(), true);
        }

        $this->detachByIdentifiers($mapper->getDatabase(), $parentIdentifiers, $childIdentifiers);
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

    /**
     * Removes the attachments between the parent and the child models
     *
     * @param Database $database The database access
     * @param array $parentIdentifiers Identifiers of the parent models
     * @param array|null $childIdentifiers Identifires of the child models
     * @param bool $detachOther If true, the given child models will stay attached but other will be detached
     * @throws DatabaseException
     */
    protected function detachByIdentifiers(
        Database $database,
        array $parentIdentifiers,
        array $childIdentifiers = null,
        bool $detachOther = false
    ) {
        try {
            $query = $database
                ->table($this->pivotTable)
                ->whereIn($this->pivotParentField, $parentIdentifiers);

            if ($childIdentifiers !== null) {
                if ($detachOther) {
                    $query->whereNotIn($this->pivotChildField, $childIdentifiers);
                } else {
                    $query->whereIn($this->pivotChildField, $childIdentifiers);
                }
            }

            $query->delete();
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }

    /**
     * Makes a database table row to represent an attachment
     *
     * @param mixed $parentId The parent model identifier in the table
     * @param mixed $childId The child model identifier in the table
     * @param array|null Additional fields to the row. The keys are the table fields names.
     * @return array The table row. The keys are the field names.
     */
    protected function makeAttachmentRow($parentId, $childId, array $extraFields = null): array
    {
        $row = [
            $this->pivotParentField => $parentId,
            $this->pivotChildField  => $childId
        ];

        return $extraFields ? $extraFields + $row : $row;
    }

    /**
     * Makes a list of additional fields to the attachment table row
     *
     * @param ModelInterface[] $parents Parent models
     * @param ModelInterface[] $children Child models
     * @param string|int $parentKey The index of the parent models array
     * @param string|int $childKey The index of the child models array
     * @param callable|null $extraFieldsMaker A function generating the attachment additional fields
     * @param string $callbackArgumentName The $extraFieldsMaker argument name for the exceptions
     * @return array|null The keys are the field names
     * @throws InvalidReturnValueException
     */
    protected function makeAttachmentExtraFields(
        array $parents,
        array $children,
        $parentKey,
        $childKey,
        callable $extraFieldsMaker = null,
        string $callbackArgumentName = '$extraFieldsMaker'
    ) {
        $extraFields = $extraFieldsMaker
            ? $extraFieldsMaker($parents[$parentKey], $children[$childKey], $parentKey, $childKey)
            : null;

        if ($extraFields !== null && !is_array($extraFields)) {
            throw new InvalidReturnValueException(sprintf(
                'The %s return value expected to be an array or null, %s given',
                $callbackArgumentName,
                is_object($extraFields) ? get_class($extraFields) : gettype($extraFields)
            ));
        }

        return $extraFields;
    }
}
