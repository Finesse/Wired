<?php

namespace Finesse\Wired\Relations;

use Finesse\MiniDB\Database;
use Finesse\Wired\Exceptions\DatabaseException;
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
     * {@inheritDoc}
     *
     * The attachments additional data are extra fields for the pivot table records (keys are field names)
     */
    public function attach(
        Mapper $mapper,
        array $parents,
        array $children,
        string $onMatch,
        bool $detachOther,
        callable $getAttachmentData = null
    ) {
        if (!$parents) {
            return;
        }

        $database = $mapper->getDatabase();
        $sampleParent = reset($parents);
        if ($children) {
            $sampleChild = reset($children);
            Helpers::checkModelObjectClass($sampleChild, $this->childModelClass);
        }
        $parentIdentifierField = $this->getParentModelIdentifierField($sampleParent);
        $childIdentifierField = $this->getChildModelIdentifierField();
        $parentIdentifiers = Helpers::getObjectsPropertyValues($parents, $parentIdentifierField, true);
        $childIdentifiers = Helpers::getObjectsPropertyValues($children, $childIdentifierField, true);

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
                    $insertAttachments = [];

                    foreach ($parents as $parentKey => $parent) {
                        foreach ($children as $childKey => $child) {
                            $extraFields = $this->makeAttachmentExtraFields(
                                $parent,
                                $child,
                                $parentKey,
                                $childKey,
                                $getAttachmentData,
                                '$getAttachmentData'
                            );
                            $insertAttachments[] = $this->makeAttachmentRow(
                                $parent->$parentIdentifierField,
                                $child->$childIdentifierField,
                                $extraFields
                            );
                        }
                    }

                    if ($insertAttachments) {
                        $database->table($this->pivotTable)->insert($insertAttachments);
                    }
                    break;

                case Mapper::UPDATE:
                    $groupedParents = Helpers::groupObjectsByProperty($parents, $parentIdentifierField, true);
                    $groupedChildren = Helpers::groupObjectsByProperty($children, $childIdentifierField, true);

                    $oldAttachments = $database
                        ->table($this->pivotTable)
                        ->whereIn($this->pivotParentField, $parentIdentifiers)
                        ->whereIn($this->pivotChildField, $childIdentifiers)
                        ->get();

                    $groupedOldAttachments = [];
                    foreach ($oldAttachments as $row) {
                        $groupedOldAttachments[$row[$this->pivotParentField]][$row[$this->pivotChildField]][] = $row;
                    }

                    $insertAttachments = [];
                    $deleteQuery = null;

                    foreach ($groupedParents as $parentId => $parentsGroup) {
                        foreach ($groupedChildren as $childId => $childrenGroup) {
                            $result = $this->updateAttachmentsGroup(
                                $parentId,
                                $childId,
                                $parentsGroup,
                                $childrenGroup,
                                $groupedOldAttachments[$parentId][$childId] ?? [],
                                $detachOther,
                                $getAttachmentData
                            );

                            if ($result['delete']) {
                                $deleteQuery = ($deleteQuery ?? $database->table($this->pivotTable))->orWhere([
                                    [$this->pivotParentField, $parentId],
                                    [$this->pivotChildField, $childId],
                                ]);
                            }

                            foreach ($result['insert'] as $row) {
                                $insertAttachments[] = $row;
                            }
                        }
                    }

                    if ($deleteQuery) {
                        $deleteQuery->delete();
                    }
                    if ($insertAttachments) {
                        $database->table($this->pivotTable)->insert($insertAttachments);
                    }
                    break;

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
     * @param array $childIdentifiers Identifires of the child models
     * @param bool $detachOther If true, the given child models will stay attached but other will be detached
     * @throws DatabaseException
     */
    protected function detachByIdentifiers(
        Database $database,
        array $parentIdentifiers,
        array $childIdentifiers,
        bool $detachOther = false
    ) {
        try {
            $query = $database->table($this->pivotTable);

            if ($detachOther) {
                $query->whereNotIn($this->pivotChildField, $childIdentifiers)->orWhereNull($this->pivotChildField);
            } else {
                $query->whereIn($this->pivotChildField, $childIdentifiers);
            }

            $query
                ->whereIn($this->pivotParentField, $parentIdentifiers)
                ->delete();
        } catch (\Throwable $exception) {
            throw Helpers::wrapException($exception);
        }
    }

    /**
     * Makes the given parents and children be attached. Updates the pivot table rows if an attachment already exists.
     * All the parents must have the same identifier as well as all the children.
     *
     * @param mixed $parentId The parent model identifier in the table
     * @param mixed $childId The child model identifier in the table
     * @param ModelInterface[] $parents The parent models
     * @param ModelInterface[] $children The child models
     * @param array[] $oldAttachments The existing attachments between the models in the database (table rows). Must be
     *  a not associative array.
     * @param bool $detachExcess If true, the excess existing attachments will be removed
     * @param callable|null $getAttachmentData A function generating the attachment additional fields
     * @return mixed[] Array with 2 keys:
     *  - 'delete' - `true` if the existing attachments must be removed from the database;
     *  - 'insert' - the pivot rows to insert to the database;
     * @throws DatabaseException
     * @throws InvalidReturnValueException
     */
    protected function updateAttachmentsGroup(
        $parentId,
        $childId,
        array $parents,
        array $children,
        array $oldAttachments,
        bool $detachExcess,
        callable $getAttachmentData = null
    ): array {
        $oldAttachmentsCount = count($oldAttachments);
        $newAttachmentsCount = count($parents) * count($children);
        $newAttachmentsExtraFields = [];
        $insertAttachments = [];
        $needsUpdate = false;

        foreach ($parents as $parentKey => $parent) {
            foreach ($children as $childKey => $child) {
                $newAttachmentsExtraFields[] = $this->makeAttachmentExtraFields(
                    $parent,
                    $child,
                    $parentKey,
                    $childKey,
                    $getAttachmentData,
                    '$getAttachmentData'
                );
            }
        }

        // Excess new attachments are inserted anyway
        if ($oldAttachmentsCount < $newAttachmentsCount) {
            for ($i = $oldAttachmentsCount; $i < $newAttachmentsCount; ++$i) {
                $insertAttachments[] = $this->makeAttachmentRow($parentId, $childId, $newAttachmentsExtraFields[$i]);
            }
            $newAttachmentsCount = $oldAttachmentsCount;
        }
        // After this line $oldAttachmentsCount >= $newAttachmentsCount

        // A single update query can be performed here when there are only 1 old and 1 new attachment

        if ($oldAttachmentsCount === $newAttachmentsCount) {
            // When number of the old attachments matches number of the new relations, there is a chance that the
            // attachments lists match and an update is not required
            for ($i = 0; $i < $oldAttachmentsCount; ++$i) {
                if (
                    $newAttachmentsExtraFields[$i] &&
                    Helpers::getFieldsToUpdate($oldAttachments[$i], $newAttachmentsExtraFields[$i])
                ) {
                    $needsUpdate = true;
                    break;
                }
            }
        } else {
            $needsUpdate = true;
        }

        // Making rows to insert (the existing are deleted)
        if ($needsUpdate) {
            for ($i = 0; $i < $oldAttachmentsCount; ++$i) {
                if ($i < $newAttachmentsCount) {
                    $insertAttachments[] = $newAttachmentsExtraFields[$i] + $oldAttachments[$i];
                } elseif ($detachExcess) {
                    break;
                } else {
                    $insertAttachments[] = $oldAttachments[$i];
                }
            }
        }

        return [
            'delete' => $needsUpdate,
            'insert' => $insertAttachments,
        ];
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
        ModelInterface $parent,
        ModelInterface $child,
        $parentKey,
        $childKey,
        callable $extraFieldsMaker = null,
        string $callbackArgumentName = '$extraFieldsMaker'
    ) {
        $extraFields = $extraFieldsMaker
            ? $extraFieldsMaker($parent, $child, $parentKey, $childKey)
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
