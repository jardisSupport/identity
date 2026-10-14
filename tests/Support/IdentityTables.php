<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Support;

use JardisSupport\Contract\DbConnection\DbConnectionInterface;

/**
 * Test helper: (re)creates the shipped identity tables from resources/sql/<driver>.sql and the
 * auth_tokens table from vendor/jardissupport/auth/resources/sql/<driver>.sql.
 */
final class IdentityTables
{
    private const TABLES = ['users', 'tenants', 'memberships', 'groups', 'group_members', 'invitations'];

    public static function recreate(DbConnectionInterface $connection, string $prefix = 'identity_'): void
    {
        $pdo = $connection->pdo();
        $driver = $connection->getDriverName();

        foreach (self::TABLES as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $prefix . $table);
        }
        $pdo->exec('DROP TABLE IF EXISTS auth_tokens');

        $identity = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/sql/' . $driver . '.sql');
        self::run($connection, str_replace('identity_', $prefix, $identity));

        $auth = (string) file_get_contents(
            dirname(__DIR__, 2) . '/vendor/jardissupport/auth/resources/sql/' . $driver . '.sql'
        );
        self::run($connection, $auth);
    }

    /** Executes a DDL file statement by statement (PDO MySQL cannot run several statements in one exec). */
    private static function run(DbConnectionInterface $connection, string $ddl): void
    {
        $withoutComments = (string) preg_replace('/^\s*--.*$/m', '', $ddl);

        foreach (explode(';', $withoutComments) as $statement) {
            if (trim($statement) !== '') {
                $connection->pdo()->exec($statement);
            }
        }
    }
}
