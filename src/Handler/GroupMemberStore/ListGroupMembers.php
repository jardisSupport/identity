<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupMemberStore;

use Closure;
use JardisSupport\DbQuery\DbQuery;

/** Lists the user ids of a group, ordered by join time. */
final class ListGroupMembers
{
    public function __construct(private readonly Closure $fetchRows, private readonly string $table)
    {
    }

    /** @return list<string> */
    public function __invoke(string $groupId): array
    {
        $rows = ($this->fetchRows)(
            (new DbQuery())->select('user_id')->from($this->table)
                ->where('group_id')->equals($groupId)
                ->orderBy('created_at')->orderBy('user_id')
        );

        return array_values(array_map(static fn (array $row): string => (string) $row['user_id'], $rows));
    }
}
