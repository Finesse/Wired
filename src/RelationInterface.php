<?php

namespace Finesse\Wired;

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
    public function applyToQueryWhere(ModelQuery $query, $constraint);

    /**
     * Loads relative models of the given models to the given models.
     *
     * @param Mapper $mapper A mapper from which new models can be obtained
     * @param string $name Relation name (for saving to the models)
     * @param ModelInterface[] $models List of models for which the relatives must be loaded. Models must have same
     *     class.
     * @param \Closure|null $constraint Relation constraint. Closure means "the relative models must fit the clause in
     *     the closure". Null means "no constraint".
     * @todo Describe exceptions
     */
    public function loadRelatives(Mapper $mapper, string $name, array $models, \Closure $constraint = null);
}
