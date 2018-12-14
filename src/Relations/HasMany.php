<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\ModelInterface;

/**
 * Models relation: a parent model has many child models, i.e. the child model belongs to the parent model.
 *
 * @author Surgie
 */
class HasMany extends EqualFields
{
    /**
     * @param string|ModelInterface $modelClass The child model class name
     * @param string $foreignField The name of the child model field which contains an identifier of a parent model
     * @param string|null $identifierField The name of the parent model field which contains a value which the foreign
     *  key targets. Null means that the default parent model identifier field should be used
     * @throws NotModelException
     */
    public function __construct(string $modelClass, string $foreignField, string $identifierField = null)
    {
        parent::__construct($identifierField, $modelClass, $foreignField, true);
    }
}
