<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\ModelInterface;

/**
 * Models relation: a parent model belongs to a child model, i.e. the child model has many parent models.
 *
 * @author Surgie
 */
class BelongsTo extends EqualFields implements AssociableRelationInterface
{
    /**
     * @param string|ModelInterface $modelClass The child model class name
     * @param string $foreignField The name of the parent model field which contains an identifier of an child model
     * @param string|null $identifierField The name of the child model field which contains a value which the foreign
     *  key targets. Null means that the default child model identifier field should be used.
     * @throws NotModelException
     */
    public function __construct(string $modelClass, string $foreignField, string $identifierField = null)
    {
        parent::__construct($foreignField, $modelClass, $identifierField, false);
    }

    /**
     * {@inheritDoc}
     */
    public function associate(string $relationName, ModelInterface $parent, ModelInterface $child)
    {
        $this->checkChildModel($child);

        $parentModelField = $this->getParentModelField(get_class($parent));
        $childModelField = $this->getChildModelField();

        if ($child->$childModelField === null) {
            throw new IncorrectModelException(
                'The associated model doesn\'t have a value in the identifier field `'.$childModelField.'`'
                    . '; perhaps it is not saved to the database'
            );
        }

        $parent->$parentModelField = $child->$childModelField;
        $parent->setLoadedRelatives($relationName, $child);
    }

    /**
     * {@inheritDoc}
     */
    public function dissociate(string $relationName, ModelInterface $parent)
    {
        $parentModelField = $this->getParentModelField(get_class($parent));

        $parent->$parentModelField = null;
        $parent->setLoadedRelatives($relationName, null);
    }
}
