<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupMemberStore;

use Closure;
use JardisSupport\DbQuery\DbQuery;

/** True if the user is a member of the group. */
final class IsGroupMember
{
    public function __construct(private readonly Closure $fetchRows, private readonly string $table)
    {
    }

    public function __invoke(string $groupId, string $userId): bool
    {
        return ($this->fetchRows)(
            (new DbQuery())->select('user_id')->from($this->table)
                ->where('group_id')->equals($groupId)
                ->and('user_id')->equals($userId)
                ->limit(1)
        ) !== [];
    }
}
