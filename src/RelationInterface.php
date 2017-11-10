<?php

namespace Finesse\Wired;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\RelationException;

/**
 * Describes a relation between models.
 *
 * @author Surgie
 */
interface RelationInterface
{
    /**
     * Applies itself to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param ModelInterface|\Closure|null $constraint Relation constraint. ModelInterface means "must be related to the
     *     specified model". Closure means "must be related to a model that fit the clause in the closure". Null means
     *     "must be related to at least one model".
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    public function applyToQueryWhere(ModelQuery $query, $constraint = null);

    /**
     * Loads relative models of the given models and puts the loaded models to the given models.
     *
     * @param Mapper $mapper A mapper from which new models can be obtained
     * @param string $name Relation name (for saving to the models)
     * @param ModelInterface[] $models List of models for which the relatives must be loaded. The models must have same
     *     class.
     * @param \Closure|null $constraint Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @param bool $onlyMissing Skip loading relatives for a model if the model already has loaded relatives
     * @throws DatabaseException
     * @throws IncorrectModelException
     */
    public function loadRelatives(
        Mapper $mapper,
        string $name,
        array $models,
        \Closure $constraint = null,
        bool $onlyMissing = false
    );
}
