<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbSqlGeneratorInterface;
use JardisSupport\Contract\Repository\Exception\PersistException;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Repository\Handler\DetectUniqueViolation;
use JardisSupport\Repository\Handler\QueryExecutor;
use PDOException;
use PDOStatement;

/**
 * Executes a dbquery write on the QueryExecutor of jardissupport/repository and maps a duplicate key
 * to UniqueViolationException (same contract exception the Repository itself throws).
 */
final class ExecuteWrite
{
    public function __construct(private readonly QueryExecutor $executor, private readonly string $table)
    {
    }

    public function __invoke(DbSqlGeneratorInterface $builder): PDOStatement
    {
        $prepared = $builder->sql($this->executor->getDialect(), prepared: true);
        if (!$prepared instanceof DbPreparedQueryInterface) {
            throw new PersistException('Identity store queries must be rendered as prepared statements');
        }

        try {
            return $this->executor->execute($prepared);
        } catch (PDOException $e) {
            $constraint = (new DetectUniqueViolation())($e, $this->executor->getDialect());
            if ($constraint !== null) {
                throw new UniqueViolationException(
                    'Unique violation on ' . $this->table . ': ' . $e->getMessage(),
                    $constraint ?: null,
                    $e,
                    [],
                    $this->table,
                );
            }

            throw new PersistException('Write failed for ' . $this->table . ': ' . $e->getMessage(), 0, $e);
        }
    }
}
