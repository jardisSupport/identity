<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\DbQuery\DbInsert;

/** Inserts a membership; a duplicate (user, tenant) throws UniqueViolationException. */
final class StoreMembership
{
    public function __construct(
        private readonly Closure $executeWrite,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(MembershipInterface $membership): void
    {
        ($this->executeWrite)(
            (new DbInsert())->into($this->table)->set([
                'user_id' => $membership->getUserId(),
                'tenant_id' => $membership->getTenantId(),
                'role' => $membership->getRole(),
                'created_at' => ($this->formatTimestamp)($membership->getCreatedAt()),
            ])
        );
    }
}
