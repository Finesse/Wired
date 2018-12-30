<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;

/**
 * A relation which can attach a parent model to a child model by changing data in a database
 *
 * @author Surgie
 */
interface AttachableRelationInterface
{
    /**
     * Creates attachments between the parent and the child models
     *
     * @param Mapper $mapper A mapper to access the database
     * @param ModelInterface[][]|mixed[][] $oarents The parent models. All the models have the same class. The value has
     *  the following format:
     *  <pre>
     *  [
     *      [
     *          ModelInterface, // A model
     *          mixed // The additional data to add to the created model attachments
     *      ],
     *      [
     *          ModelInterface,
     *          mixed
     *      ],
     *      ...
     *  ]
     *  </pre>
     * @param ModelInterface[][]|mixed[][] $children The child models. Has the same format as the parent models.
     * @param string $onMatch What to do when a given attachment matches an existing attachment. Possible values:
     *  - 'update' or Mapper::UPDATE,
     *  - 'replace' or Mapper::REPLACE,
     *  - 'duplicate' or Mapper::DUPLICATE
     * @param bool $detachOther If true, the child models that are not in the given children list will be detached from
     *  the given parent models
     * @return void
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function attach(Mapper $mapper, array $parents, array $children, string $onMatch, bool $detachOther);

    /**
     * Removes attachments between the parent and the child models
     *
     * @param Mapper $mapper A mapper to access the database
     * @param ModelInterface[] $oarents The parent models. All the models have the same class.
     * @param ModelInterface[] $children The child models. All the models have the same class.
     * @return void
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function detach(Mapper $mapper, array $parents, array $children);
}
