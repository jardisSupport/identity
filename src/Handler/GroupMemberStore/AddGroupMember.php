<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupMemberStore;

use Closure;
use DateTimeImmutable;
use JardisSupport\DbQuery\DbInsert;

/** Inserts a group membership; a duplicate (group, user) throws UniqueViolationException. */
final class AddGroupMember
{
    public function __construct(
        private readonly Closure $executeWrite,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $groupId, string $userId): void
    {
        ($this->executeWrite)(
            (new DbInsert())->into($this->table)->set([
                'group_id' => $groupId,
                'user_id' => $userId,
                'created_at' => ($this->formatTimestamp)(new DateTimeImmutable('now')),
            ])
        );
    }
}
