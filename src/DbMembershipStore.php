<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Identity\Handler\MembershipStore\FindMembership;
use JardisSupport\Identity\Handler\MembershipStore\HydrateMembership;
use JardisSupport\Identity\Handler\MembershipStore\ListMemberships;
use JardisSupport\Identity\Handler\MembershipStore\RemoveMembership;
use JardisSupport\Identity\Handler\MembershipStore\StoreMembership;
use JardisSupport\Identity\Handler\MembershipStore\UpdateMembershipRole;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\ExecuteWrite;
use JardisSupport\Identity\Handler\Shared\FetchRows;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Identity\Handler\Shared\ParseUtcTimestamp;
use JardisSupport\Repository\Handler\QueryExecutor;

/**
 * Persists memberships (composite key user + tenant) via jardissupport/dbquery, executed through the
 * QueryExecutor of jardissupport/repository.
 *
 * Table DDL ships in resources/sql/<driver>.sql; the table is "<prefix>memberships".
 */
final class DbMembershipStore implements MembershipStoreInterface
{
    private readonly Closure $storeMembership;
    private readonly Closure $updateRole;
    private readonly Closure $removeMembership;
    private readonly Closure $findMembership;
    private readonly Closure $listForUser;
    private readonly Closure $listForTenant;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $table = (new AssertTablePrefix())->__invoke($prefix) . 'memberships';
        $executor = new QueryExecutor($connection);
        $write = (new ExecuteWrite($executor, $table))->__invoke(...);
        $fetch = (new FetchRows($executor))->__invoke(...);
        $format = (new FormatUtcTimestamp())->__invoke(...);
        $hydrate = (new HydrateMembership((new ParseUtcTimestamp())->__invoke(...)))->__invoke(...);

        $this->storeMembership = (new StoreMembership($write, $format, $table))->__invoke(...);
        $this->updateRole = (new UpdateMembershipRole($write, $table))->__invoke(...);
        $this->removeMembership = (new RemoveMembership($write, $table))->__invoke(...);
        $this->findMembership = (new FindMembership($fetch, $hydrate, $table))->__invoke(...);
        $this->listForUser = (new ListMemberships($fetch, $hydrate, $table, 'user_id', 'tenant_id'))->__invoke(...);
        $this->listForTenant = (new ListMemberships($fetch, $hydrate, $table, 'tenant_id', 'user_id'))->__invoke(...);
    }

    public function store(MembershipInterface $membership): void
    {
        ($this->storeMembership)($membership);
    }

    public function updateRole(string $userId, string $tenantId, string $role): void
    {
        ($this->updateRole)($userId, $tenantId, $role);
    }

    public function remove(string $userId, string $tenantId): void
    {
        ($this->removeMembership)($userId, $tenantId);
    }

    public function find(string $userId, string $tenantId): ?MembershipInterface
    {
        return ($this->findMembership)($userId, $tenantId);
    }

    public function listForUser(string $userId): array
    {
        return ($this->listForUser)($userId);
    }

    public function listForTenant(string $tenantId): array
    {
        return ($this->listForTenant)($tenantId);
    }
}
