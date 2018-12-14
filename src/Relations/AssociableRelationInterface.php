<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\RelationInterface;

/**
 * A relation which can associate a parent instance to a child instance without changing the child instance and
 * changing anything in a database.
 *
 * @author Surgie
 */
interface AssociableRelationInterface extends RelationInterface
{
    /**
     * Attaches the child model to the parent model. Add the child model to the loaded relatives of the parent model.
     *
     * @param string $relationName This relation name
     * @param ModelInterface $parent
     * @param ModelInterface $child
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function associate(string $relationName, ModelInterface $parent, ModelInterface $child);

    /**
     * Detaches an attached child model from the parent model. Removes the child model from the loaded relatives of
     * the parent model.
     *
     * @param string $relationName This relation name
     * @param ModelInterface $parent
     * @throws RelationException
     * @throws IncorrectModelException
     */
    public function dissociate(string $relationName, ModelInterface $parent);
}
