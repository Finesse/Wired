<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\ModelInterface;
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
    public $modelClass;

    /**
     * @var string The name of the subject model field which contains an identifier of an object model
     */
    public $foreignField;

    /**
     * @var string|null The name of the object model field which contains a value which the foreign key targets. Null
     *     means that the default object model identifier field should be used.
     */
    public $identifierField;

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
}
