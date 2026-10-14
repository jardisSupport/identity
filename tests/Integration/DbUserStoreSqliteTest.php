<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Identity\Tests\Support\Connections;

/** DbUserStore against in-memory SQLite (second dialect, no service needed). */
final class DbUserStoreSqliteTest extends DbUserStoreTestCase
{
    protected function createConnection(): DbConnectionInterface
    {
        return Connections::sqlite();
    }
}
