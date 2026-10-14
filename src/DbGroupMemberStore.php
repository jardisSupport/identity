<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Identity\Handler\GroupMemberStore\AddGroupMember;
use JardisSupport\Identity\Handler\GroupMemberStore\IsGroupMember;
use JardisSupport\Identity\Handler\GroupMemberStore\ListGroupMembers;
use JardisSupport\Identity\Handler\GroupMemberStore\RemoveGroupMember;
use JardisSupport\Identity\Handler\GroupMemberStore\RemoveUserFromTenantGroups;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\ExecuteWrite;
use JardisSupport\Identity\Handler\Shared\FetchRows;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Repository\Handler\QueryExecutor;

/**
 * Persists group memberships (composite key group + user) via jardissupport/dbquery, executed through
 * the QueryExecutor of jardissupport/repository.
 *
 * Table DDL ships in resources/sql/<driver>.sql; the table is "<prefix>group_members".
 */
final class DbGroupMemberStore implements GroupMemberStoreInterface
{
    private readonly Closure $addMember;
    private readonly Closure $removeMember;
    private readonly Closure $removeFromTenantGroups;
    private readonly Closure $listMembers;
    private readonly Closure $isMember;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $prefix = (new AssertTablePrefix())->__invoke($prefix);
        $table = $prefix . 'group_members';
        $executor = new QueryExecutor($connection);
        $write = (new ExecuteWrite($executor, $table))->__invoke(...);
        $fetch = (new FetchRows($executor))->__invoke(...);
        $format = (new FormatUtcTimestamp())->__invoke(...);

        $this->addMember = (new AddGroupMember($write, $format, $table))->__invoke(...);
        $this->removeMember = (new RemoveGroupMember($write, $table))->__invoke(...);
        $this->removeFromTenantGroups = (new RemoveUserFromTenantGroups($write, $fetch, $table, $prefix . 'groups'))
            ->__invoke(...);
        $this->listMembers = (new ListGroupMembers($fetch, $table))->__invoke(...);
        $this->isMember = (new IsGroupMember($fetch, $table))->__invoke(...);
    }

    public function add(string $groupId, string $userId): void
    {
        ($this->addMember)($groupId, $userId);
    }

    public function remove(string $groupId, string $userId): void
    {
        ($this->removeMember)($groupId, $userId);
    }

    public function removeUserFromTenantGroups(string $userId, string $tenantId): void
    {
        ($this->removeFromTenantGroups)($userId, $tenantId);
    }

    public function listMembers(string $groupId): array
    {
        return ($this->listMembers)($groupId);
    }

    public function isMember(string $groupId, string $userId): bool
    {
        return ($this->isMember)($groupId, $userId);
    }
}
