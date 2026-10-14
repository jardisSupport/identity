<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Identity\Tests\Support\Connections;

/** DbGroupStore against in-memory SQLite (second dialect, no service needed). */
final class DbGroupStoreSqliteTest extends DbGroupStoreTestCase
{
    protected function createConnection(): DbConnectionInterface
    {
        return Connections::sqlite();
    }
}
