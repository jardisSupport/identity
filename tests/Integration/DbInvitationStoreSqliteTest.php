<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Identity\Tests\Support\Connections;

/** DbInvitationStore against in-memory SQLite (second dialect, no service needed). */
final class DbInvitationStoreSqliteTest extends DbInvitationStoreTestCase
{
    protected function createConnection(): DbConnectionInterface
    {
        return Connections::sqlite();
    }
}
