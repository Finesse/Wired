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
     * @param array $arguments Parameters for application
     * @throws RelationException
     * @throws InvalidArgumentException
     */
    public function applyToQueryWhere(ModelQuery $query, array $arguments);
}
