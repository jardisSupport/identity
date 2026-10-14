<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use InvalidArgumentException;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbSqlGeneratorInterface;
use JardisSupport\Repository\Handler\QueryExecutor;

/** Renders a dbquery SELECT for the executor's dialect and returns all rows. */
final class FetchRows
{
    public function __construct(private readonly QueryExecutor $executor)
    {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(DbSqlGeneratorInterface $builder): array
    {
        $prepared = $builder->sql($this->executor->getDialect(), prepared: true);
        if (!$prepared instanceof DbPreparedQueryInterface) {
            throw new InvalidArgumentException('Identity store queries must be rendered as prepared statements');
        }

        return array_values($this->executor->fetchAll($prepared));
    }
}
