<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Identity\Tests\Support\Connections;

/** DbInvitationStore against the real MariaDB service of the package's docker-compose.yml. */
final class DbInvitationStoreMariaDbTest extends DbInvitationStoreTestCase
{
    protected function createConnection(): DbConnectionInterface
    {
        return Connections::mariaDb();
    }
}
