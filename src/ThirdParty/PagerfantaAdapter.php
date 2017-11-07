<?php

namespace Finesse\Wired\ThirdParty;

use Finesse\Wired\Exceptions\DatabaseException;
use Finesse\Wired\Exceptions\IncorrectQueryException;
use Finesse\Wired\Model;
use Finesse\Wired\ModelQuery;
use Pagerfanta\Adapter\AdapterInterface;

/**
 * Pagerfanta adapter
 *
 * @see https://github.com/whiteoctober/Pagerfanta Pagerfanta
 * @author Surgie
 */
class PagerfantaAdapter implements AdapterInterface
{
    /**
     * @var ModelQuery A query from which the models should be taken
     */
    protected $query;

    /**
     * @param ModelQuery $query A query from which the models should be taken. Warning, it will be modified.
     */
    public function __construct(ModelQuery $query)
    {
        $this->query = $query;
    }

    /**
     * {@inheritDoc}
     * @throws DatabaseException
     * @throws IncorrectQueryException
     */
    public function getNbResults()
    {
        return $this->query->count();
    }

    /**
     * {@inheritDoc}
     * @return Model[]
     * @throws DatabaseException
     * @throws IncorrectQueryException
     */
    public function getSlice($offset, $length)
    {
        return $this->query->offset($offset)->limit($length)->get();
    }
}
