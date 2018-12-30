<?php

namespace Finesse\Wired\MapperFeatures;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;
use Finesse\Wired\Relations\AttachableRelationInterface;

/**
 * A set of methods to manage models attachments (described by AttachableRelationInterface)
 *
 * @todo The `attach` method
 * @mixin Mapper
 * @author Surgie
 */
trait AttachTrait
{
    /**
     * Removes attachments between the given models
     *
     * @todo Documentation
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

        $groupedModels1 = Helpers::groupModelsByClass($parents);
        $groupedModels2 = Helpers::groupModelsByClass($children);

        foreach ($groupedModels1 as $parents) {
            foreach ($groupedModels2 as $children) {
                $this->detachModelsOfSameClass($parents, $relationName, $children);
            }
        }
    }

    /**
     * Removes attachments between the given models when models have same classes
     *
     * @param ModelInterface[] $parents The models on the parent side of the relation. Not empty. All has the same class.
     * @param string $relationName The relation name which attaches the models
     * @param ModelInterface[] $children The models on the child side of the relation. Not empty. All has the same class.
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws RelationException
     */
    protected function detachModelsOfSameClass(array $parents, string $relationName, array $children)
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
