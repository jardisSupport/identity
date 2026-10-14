<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Identity\Tests\Support\Connections;

/** DbGroupStore against the real MariaDB service of the package's docker-compose.yml. */
final class DbGroupStoreMariaDbTest extends DbGroupStoreTestCase
{
    protected function createConnection(): DbConnectionInterface
    {
        return Connections::mariaDb();
    }
}
