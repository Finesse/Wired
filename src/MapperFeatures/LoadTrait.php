<?php

namespace Finesse\Wired\MapperFeatures;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\RelationException;
use Finesse\Wired\Helpers;
use Finesse\Wired\Mapper;
use Finesse\Wired\ModelInterface;

/**
 * A set of methods to load relative models of another models
 *
 * @mixin Mapper
 * @author Surgie
 */
trait LoadTrait
{
    /**
     * Loads relative models of the given models and puts the loaded models to the given models.
     *
     * @param ModelInterface|ModelInterface[] $models A model or an array of models
     * @param string $relationName The relation name from which the relative models should be loaded. If you need to
     *     load a subrelations too, add their names separated by a dot.
     * @param \Closure|null $clause Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @param bool $onlyMissing Skip loading relatives for a model if the model already has loaded relatives
     * @throws RelationException
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function load($models, string $relationName, \Closure $clause = null, bool $onlyMissing = false)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach (Helpers::groupModelsByClass($models) as $sameClassModels) {
            $this->loadRelativesForModelsOfSameClass($sameClassModels, $relationName, $clause, $onlyMissing);
        }
    }

    /**
     * Loads relative models of the given models, then loads related models of the related models and so on... Puts the
     * loaded models to the given and relative models.
     *
     * @param ModelInterface|ModelInterface[] $models A model or an array of models
     * @param string $relationName The relation name from which the relative models should be loaded. The relation end
     *     model must also have this relation. If the model is cycled throw a related model, specify all the chain
     *     relations names separated by a dot.
     * @param \Closure|null $clause Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @param bool $onlyMissing Skip loading relatives for a model if the model already has loaded relatives
     * @throws RelationException
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function loadCyclic($models, string $relationName, \Closure $clause = null, bool $onlyMissing = false)
    {
        if (!is_array($models)) {
            $models = [$models];
        }

        foreach (Helpers::groupModelsByClass($models) as $sameClassModels) {
            $this->loadCyclicRelativesForModelsOfSameClass($sameClassModels, $relationName, $clause, $onlyMissing);
        }
    }

    /**
     * Loads relative models of the given models with same class and puts the loaded models to the given models. The
     * loaded relatives have the same class.
     *
     * @param ModelInterface[] $models Array of models. The models must have the same class.
     * @param string $relationName The relation name from which the relative models should be loaded. If you need to
     *     load a subrelations too, add their names separated by a dot.
     * @param \Closure|null $clause Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @param bool $onlyMissing Skip loading relatives for a model if the model already has loaded relatives
     * @return ModelInterface[] The models from the penultimate chain level. If the chain has a single relation, the
     *     original models list is returned.
     * @throws RelationException
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    protected function loadRelativesForModelsOfSameClass(
        array $models,
        string $relationName,
        \Closure $clause = null,
        bool $onlyMissing = false
    ): array {
        $relationsChain = explode('.', $relationName);

        for ($i = 0, $l = count($relationsChain); $i < $l; ++$i) {
            if (!$models) {
                break;
            }

            $relationName = $relationsChain[$i];
            $isLastRelation = $i === $l - 1;
            $sampleModel = reset($models);
            $relation = $sampleModel::getRelationOrFail($relationName);
            $modelsToLoad = $models;

            // Filter out the models whose relatives are already loaded (their relatives won't be loaded)
            if (!$isLastRelation || $onlyMissing) {
                $modelsToLoad = array_filter($modelsToLoad, function (ModelInterface $model) use ($relationName) {
                    return !$model->doesHaveLoadedRelatives($relationName);
                });
            }

            $relation->loadRelatives($this, $relationName, $modelsToLoad, $isLastRelation ? $clause : null);

            if (!$isLastRelation) {
                $models = Helpers::collectModelsRelatives($models, $relationName);
            }
        }

        return $models;
    }

    /**
     * Loads relative models of the given models with the same class, then loads related models of the related models
     * and so on... Puts the loaded models to the given and relative models.
     *
     * @param ModelInterface[] $models Not empty array of models. The models must have the same class.
     * @param string $relationName The relation name from which the relative models should be loaded. The relation end
     *     model must also have this relation. If the model is cycled throw a related model, specify all the chain
     *     relations names separated by a dot.
     * @param \Closure|null $clause Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @param bool $onlyMissing Skip loading relatives for a model if the model already has loaded relatives
     * @throws RelationException
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    protected function loadCyclicRelativesForModelsOfSameClass(
        array $models,
        string $relationName,
        \Closure $clause = null,
        bool $onlyMissing = false
    ) {
        $sampleModel = reset($models);
        $relationsNames = explode('.', $relationName);
        $lastRelationName = end($relationsNames);
        $isFirstLevel = true;

        // All the loaded relatives are kept to prevent a recursion
        $loadedModels = [
            get_class($sampleModel) => Helpers::indexObjectsByProperty($models, $sampleModel::getIdentifierField())
        ];

        // Cycles level by level
        while ($models) {
            try {
                $penultimateModelsLevel = $this->loadRelativesForModelsOfSameClass(
                    $models,
                    $relationName,
                    $clause,
                    $onlyMissing
                );
            } catch (RelationException $exception) {
                if ($isFirstLevel) {
                    throw $exception;
                } else {
                    throw new RelationException(
                        $exception->getMessage().'; perhaps, the given relation is not cycled',
                        $exception->getCode(),
                        $exception
                    );
                }
            }

            $models = [];

            // Just for cache
            $relativeClass = null;
            $relativeIdentifierField = null;

            // Replaces the loaded relatives, which are already loaded, with the old loaded relatives.
            // The replaced relatives are not loaded in the next iteration.
            // It prevents the algorithm from an infinite cycle.
            Helpers::filterModelsRelatives(
                $penultimateModelsLevel,
                $lastRelationName,
                function (ModelInterface $relative) use (
                    &$relativeClass, &$relativeIdentifierField, &$loadedModels, &$models
                ) {
                    if ($relativeClass === null) {
                        $relativeClass = get_class($relative);
                        $relativeIdentifierField = $relative::getIdentifierField();
                    }

                    $id = $relative->$relativeIdentifierField;

                    // Catch a recursion
                    if (isset($loadedModels[$relativeClass][$id])) {
                        return $loadedModels[$relativeClass][$id];
                    }

                    $loadedModels[$relativeClass][$id] = $relative;
                    $models[] = $relative;
                    return true;
                }
            );

            $isFirstLevel = false;
        }
    }
}
