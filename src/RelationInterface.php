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
     * @param ModelInterface|\Closure|null $target Relation target. ModelInterface means "must be related to the
     *     specified model". Closure means "must be related to a model that fit the clause in the closure". Null means
     *     "must be related to at least one model".
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    public function applyToQueryWhere(ModelQuery $query, $target);
}
