<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Support;

use JardisAdapter\DbConnection\Factory\ConnectionFactory;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;

/** Test helper: the two connections every store test runs against. */
final class Connections
{
    public static function sqlite(): DbConnectionInterface
    {
        return (new ConnectionFactory())->sqlite();
    }

    public static function mariaDb(): DbConnectionInterface
    {
        return (new ConnectionFactory())->mysql(
            host: (string) getenv('MARIADB_HOST'),
            user: (string) getenv('MARIADB_USER'),
            password: (string) getenv('MARIADB_PASSWORD'),
            database: (string) getenv('MARIADB_DATABASE'),
            port: (int) getenv('MARIADB_PORT'),
        );
    }
}
