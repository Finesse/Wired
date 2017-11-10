<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Relations\AssociableRelationInterface;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\ModelInterface;

/**
 * Models relation: a subject model belongs to an object model, i.e. the object model has many subject models.
 *
 * @author Surgie
 */
class BelongsTo extends CompareFields implements AssociableRelationInterface
{
    /**
     * @param string|ModelInterface $modelClass The object model class name
     * @param string $foreignField The name of the subject model field which contains an identifier of an object model
     * @param string|null $identifierField The name of the object model field which contains a value which the foreign
     *     key targets. Null means that the default object model identifier field should be used.
     * @throws NotModelException
     */
    public function __construct(string $modelClass, string $foreignField, string $identifierField = null)
    {
        parent::__construct($foreignField, '=', $modelClass, $identifierField, false);
    }

    /**
     * {@inheritDoc}
     */
    public function associate(string $relationName, ModelInterface $subject, ModelInterface $object)
    {
        $this->checkObjectModel($object);

        $subjectModelField = $this->getSubjectModelField(get_class($subject));
        $objectModelField = $this->getObjectModelField();

        if ($object->$objectModelField === null) {
            throw new IncorrectModelException(
                'The associated model doesn\'t have a value in the identifier field `'.$objectModelField.'`'
                    . '; perhaps it is not saved to the database'
            );
        }

        $subject->$subjectModelField = $object->$objectModelField;
        $subject->setLoadedRelatives($relationName, $object);
    }

    /**
     * {@inheritDoc}
     */
    public function dissociate(string $relationName, ModelInterface $subject)
    {
        $subjectModelField = $this->getSubjectModelField(get_class($subject));

        $subject->$subjectModelField = null;
        $subject->setLoadedRelatives($relationName, null);
    }
}
