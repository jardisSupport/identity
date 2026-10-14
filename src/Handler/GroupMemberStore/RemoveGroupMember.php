<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupMemberStore;

use Closure;
use JardisSupport\DbQuery\DbDelete;

/** Deletes a group membership (unknown pair is a no-op). */
final class RemoveGroupMember
{
    public function __construct(private readonly Closure $executeWrite, private readonly string $table)
    {
    }

    public function __invoke(string $groupId, string $userId): void
    {
        ($this->executeWrite)(
            (new DbDelete())->from($this->table)
                ->where('group_id')->equals($groupId)
                ->and('user_id')->equals($userId)
        );
    }
}
