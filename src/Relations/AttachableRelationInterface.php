<?php

namespace Finesse\Wired\Relations;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
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
     * @param ModelInterface[] $parents The parent models. All the models has the same class.
     * @param ModelInterface[] $children The child models. All the models has the same class.
     * @param string $onMatch What to do when a given attachment matches an existing attachment. Possible values:
     *  - 'update' or Mapper::UPDATE,
     *  - 'replace' or Mapper::REPLACE,
     *  - 'duplicate' or Mapper::DUPLICATE
     * @param bool $detachOther If true, the child models that are not in the given children list will be detached from
     *  the given parent models
     * @param callable|null $getAttachmentData Makes additional data for the created attachments. Takes arguments:
     *  - Parent model,
     *  - Child model,
     *  - Parent model key in the $parents list,
     *  - Child model key in the $children list
     * @return void
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     * @throws InvalidArgumentException
     */
    public function attach(
        Mapper $mapper,
        array $parents,
        array $children,
        string $onMatch,
        bool $detachOther,
        callable $getAttachmentData = null
    );

    /**
     * Removes attachments of the parent models
     *
     * @param Mapper $mapper A mapper to access the database
     * @param ModelInterface[] $parents The parent models. All the models has the same class.
     * @param ModelInterface[]|null $children The child models. All the models has the same class.
     * @return void
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function detach(Mapper $mapper, array $parents, array $children);
}
