<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use Throwable;

/** Runs a unit of work in one transaction: commit on success, rollback and rethrow on any Throwable. */
final class RunInTransaction
{
    public function __construct(private readonly DbConnectionInterface $connection)
    {
    }

    /**
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function __invoke(Closure $work): mixed
    {
        // An already open transaction belongs to the caller: join it instead of nesting.
        if ($this->connection->inTransaction()) {
            return $work();
        }

        $this->connection->beginTransaction();
        try {
            $result = $work();
            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollback();
            }

            throw $e;
        }
    }
}
