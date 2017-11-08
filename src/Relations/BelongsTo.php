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
    public function applyToQueryWhere(ModelQuery $query, array $arguments)
    {
        $target = $arguments[0] ?? null;

        if ($target instanceof ModelInterface) {
            if (!$target instanceof $this->modelClass) {
                throw new RelationException(sprintf(
                    'The given model %s is not a %s model',
                    get_class($target),
                    $this->modelClass
                ));
            }

            $query->where(
                $this->foreignField,
                $target->{$this->identifierField ?? $target::getIdentifierField()}
            );
            return;
        }

        if ($target === null || $target instanceof \Closure) {
            $query->whereExists(function (ModelQuery $subQuery) use ($query, $target) {
                $baseQuery = $query->getBaseQuery();
                $queryTableName = $baseQuery->tableAlias ?? $baseQuery->table;
                $subQueryTable = $this->modelClass::getTable();
                $subQueryTableAlias = null;

                if ($subQueryTable === $queryTableName) {
                    $counter = 0;
                    do {
                        $subQueryTableAlias = '__wired_reserved_alias_'.$counter++;
                    } while ($subQueryTableAlias === $queryTableName);
                }

                $subQueryTableName = $subQueryTableAlias ?? $subQueryTable;

                $subQuery->setModelClass($this->modelClass);
                $subQuery->table($subQueryTable, $subQueryTableAlias);
                $subQuery->whereColumn(
                    $queryTableName.'.'.$this->foreignField,
                    $subQueryTableName.'.'.($this->identifierField ?? $this->modelClass::getIdentifierField())
                );

                return $target ? $target($subQuery) : $subQuery;
            });
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'The relation argument expected to be %s, %s or null, %s given',
            ModelInterface::class,
            \Closure::class,
            is_object($target) ? get_class($target) : gettype($target)
        ));
    }
}
