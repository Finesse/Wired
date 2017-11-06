<?php

namespace Finesse\Wired\Exceptions;

use Finesse\MiniDB\Exceptions\DatabaseException as DBDatabaseException;

/**
 * {@inheritDoc}
 *
 * @author Surgie
 */
class DatabaseException extends DBDatabaseException implements ExceptionInterface {}
