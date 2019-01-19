<?php

namespace Finesse\Wired\MapperFeatures;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\Relations\AttachableRelationInterface;

/**
 * A set of methods to manage models attachments (described by AttachableRelationInterface)
 *
 * @todo Documentation
 * @mixin Mapper
 * @author Surgie
 */
trait AttachTrait
{
    /**
     * Add attachments between the given models. May add duplicates if attachments already exist.
     *
     * @param ModelInterface|ModelInterface[] $parents The models on the parent side of the relation
     * @param string $relationName The relation name which should attache the models
     * @param ModelInterface|ModelInterface[] $children The models on the child side of the relation
     * @param callable|null $getAttachmentData Makes additional fields for the created attachments. Takes arguments:
     *  - Parent model,
     *  - Child model,
     *  - Parent model key in the $parents list,
     *  - Child model key in the $children list
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     * @throws RelationException
     * @throws NotModelException
     */
    public function attach($parents, string $relationName, $children, callable $getAttachmentData = null)
    {
        $this->attachWithDetails($parents, $relationName, $children, Mapper::DUPLICATE, false, $getAttachmentData);
    }

    /**
     * Makes the given parent models have only the given child models
     *
     * @param ModelInterface|ModelInterface[] $parents The models on the parent side of the relation
     * @param string $relationName The relation name which should attache the models
     * @param ModelInterface|ModelInterface[] $children The models on the child side of the relation
     * @param bool $keepOther If true, no models will be detached
     * @param callable|null $getAttachmentData Makes additional fields for the created attachments. Takes arguments:
     *  - Parent model,
     *  - Child model,
     *  - Parent model key in the $parents list,
     *  - Child model key in the $children list
     * @param bool $simpleMode If true, the existing models attachments will be removed and inserted again which can
     *  cause a data loss (is the attachments have some extra data). If false, no attachment data will be lost but is
     *  has a higher risk of an error caused by a race condition.
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     * @throws RelationException
     * @throws NotModelException
     */
    public function setAttachments(
        $parents,
        string $relationName,
        $children,
        bool $keepOther = false,
        callable $getAttachmentData = null,
        bool $simpleMode = false
    ) {
        $this->attachWithDetails(
            $parents,
            $relationName,
            $children,
            $simpleMode ? Mapper::REPLACE : Mapper::UPDATE,
            !$keepOther,
            $getAttachmentData
        );
    }

    /**
     * Removes attachments between the given models
     *
     * @param ModelInterface|ModelInterface[] $parents The models on the parent side of the relation
     * @param string $relationName The relation name which attaches the models
     * @param ModelInterface|ModelInterface[] $children The models on the child side of the relation
     * @throws DatabaseException
     * @throws NotModelException
     * @throws IncorrectModelException
     * @throws RelationException
     */
    public function detach($parents, string $relationName, $children)
    {
        if (!is_array($parents)) {
            $parents = [$parents];
        }
        if (!is_array($children)) {
            $children = [$children];
        }

        $groupedParents = Helpers::groupModelsByClass($parents);
        $groupedChildren = Helpers::groupModelsByClass($children);

        foreach ($groupedParents as $parents) {
            foreach ($groupedChildren as $children) {
                $this->detachModelsOfSameClass($parents, $relationName, $children);
            }
        }
    }

    /**
     * Removes attachments of the given models
     *
     * @param ModelInterface|ModelInterface[] $models The models
     * @param string $relationName The relation name of the attachments to remove
     * @throws DatabaseException
     * @throws NotModelException
     * @throws IncorrectModelException
     * @throws RelationException
     */
    public function detachAll($models, string $relationName)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach (Helpers::groupModelsByClass($models) as $sameClassModels) {
            $this->detachModelsOfSameClass($sameClassModels, $relationName);
        }
    }

    /**
     * Creates attachments between the given models. Takes raw attachment parameters to pass to the
     * AttachableRelationInterface::attach method.
     *
     * @param ModelInterface|ModelInterface[] $parents The models on the parent side of the relation
     * @param string $relationName The relation name which should attache the models
     * @param ModelInterface|ModelInterface[] $children The models on the child side of the relation
     * @see AttachableRelationInterface::attach for the other arguments
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     * @throws RelationException
     * @throws NotModelException
     */
    protected function attachWithDetails(
        $parents,
        string $relationName,
        $children,
        string $onMatch,
        bool $detachOther,
        callable $getAttachmentData = null
    ) {
        if (!is_array($parents)) {
            $parents = [$parents];
        }
        if (!is_array($children)) {
            $children = [$children];
        }

        $groupedParents = Helpers::groupModelsByClass($parents);
        $groupedChildren = Helpers::groupModelsByClass($children);

        foreach ($groupedParents as $parents) {
            foreach ($groupedChildren as $children) {
                $this->attachModelsOfSameClass($parents, $relationName, $children, $onMatch, $detachOther, $getAttachmentData);
            }
        }
    }

    /**
     * Creates attachments between the given models when models have same classes
     *
     * @param string $relationName The relation name which should attache the models
     * @see AttachableRelationInterface::attach for the other arguments
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     * @throws RelationException
     */
    protected function attachModelsOfSameClass(
        array $parents,
        string $relationName,
        array $children,
        string $onMatch,
        bool $detachOther,
        callable $getAttachmentData = null
    ) {
        $sampleModel = reset($parents);
        $relation = $sampleModel::getRelationOrFail($relationName);

        if ($relation instanceof AttachableRelationInterface) {
            $relation->attach($this, $parents, $children, $onMatch, $detachOther, $getAttachmentData);
            return;
        }

        throw new RelationException("Attaching is not available for the `$relationName` relation of the ".get_class($sampleModel)." model");
    }

    /**
     * Removes attachments of the given models when models have same classes
     *
     * @param ModelInterface[] $parents The models on the parent side of the relation. Not empty, all has the same class.
     * @param string $relationName The relation name which attaches the models
     * @param ModelInterface[] $children If not null, only attachments to the given child side models are removed. If
     *  array, not empty and all has the same class.
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws RelationException
     */
    protected function detachModelsOfSameClass(array $parents, string $relationName, array $children = null)
    {
        $sampleModel = reset($parents);
        $relation = $sampleModel::getRelationOrFail($relationName);

        if ($relation instanceof AttachableRelationInterface) {
            $relation->detach($this, $parents, $children);
            return;
        }

        throw new RelationException("Detaching is not available for the `$relationName` relation of the ".get_class($sampleModel)." model");
    }
}
