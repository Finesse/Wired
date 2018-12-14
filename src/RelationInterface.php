<?php

namespace Finesse\Wired;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectModelException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Exceptions\InvalidArgumentException;
use Finesse\Wired\Exceptions\InvalidReturnValueException;
use Finesse\Wired\Exceptions\NotModelException;
use Finesse\Wired\Exceptions\RelationException;

/**
 * Describes a relation between models.
 *
 * The Parent and Child terms are used here. Parent is the model which has the relation. Child is the related model.
 *
 * @author Surgie
 */
interface RelationInterface
{
    /**
     * Applies itself to the where part of a query.
     *
     * @param ModelQuery $query Where to apply
     * @param ModelInterface|ModelInterface[]|\Closure|null $constraint Relation constraint. ModelInterface means "must
     *  be related to the specified model". Models array means "must be related to one of the specified models". Closure
     *  means "must be related to a model that fit the clause in the closure". Null means "must be related to at least
     *  one model".
     * @throws RelationException
     * @throws NotModelException
     * @throws InvalidArgumentException
     * @throws InvalidReturnValueException
     * @throws IncorrectModelException
     * @throws IncorrectQueryException
     */
    public function applyToQueryWhere(ModelQuery $query, $constraint = null);

    /**
     * Loads relative models of the given models and puts the loaded models to the given models. Overrides a model
     * loaded relatives if it has some. The loaded relatives must have same class.
     *
     * @param Mapper $mapper A mapper from which new models can be obtained
     * @param string $name Relation name (for saving to the models)
     * @param ModelInterface[] $models List of models for which the relatives must be loaded. The models must have same
     *  class.
     * @param \Closure|null $constraint Relation constraint. Closure means "the relative models must fit the clause in
     *  the closure". Null means "no constraint".
     * @throws DatabaseException
     * @throws IncorrectModelException
     * @throws InvalidReturnValueException
     */
    public function loadRelatives(Mapper $mapper, string $name, array $models, \Closure $constraint = null);
}
