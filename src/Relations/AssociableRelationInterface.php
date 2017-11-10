<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\RelationInterface;

/**
 * A relation which can associate an object instance to a subject instance without changing the object instance and
 * creating anything in a database.
 *
 * @author Surgie
 */
interface AssociableRelationInterface extends RelationInterface
{
    /**
     * Attaches the object model to the subject model. Add the object model to the loaded relatives of the subject
     * model.
     *
     * @param string $relationName This relation name
     * @param ModelInterface $subject
     * @param ModelInterface $object
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function associate(string $relationName, ModelInterface $subject, ModelInterface $object);

    /**
     * Detaches an attached object model from the subject model. Removes the object model from the loaded relatives of
     * the subject model.
     *
     * @param string $relationName This relation name
     * @param ModelInterface $subject
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function dissociate(string $relationName, ModelInterface $subject);
}
