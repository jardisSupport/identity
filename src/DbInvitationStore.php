<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\Contract\Identity\InvitationStoreInterface;
use JardisSupport\Identity\Handler\InvitationStore\FindInvitationByTokenHash;
use JardisSupport\Identity\Handler\InvitationStore\HydrateInvitation;
use JardisSupport\Identity\Handler\InvitationStore\ListPendingInvitations;
use JardisSupport\Identity\Handler\InvitationStore\MarkInvitationAccepted;
use JardisSupport\Identity\Handler\InvitationStore\MarkInvitationRevoked;
use JardisSupport\Identity\Handler\InvitationStore\StoreInvitation;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Identity\Handler\Shared\ParseUtcTimestamp;
use JardisSupport\Repository\Repository;

/**
 * Persists invitations on a database connection via jardissupport/repository.
 *
 * Stores only the token hash. Table DDL ships in resources/sql/<driver>.sql; the table is
 * "<prefix>invitations".
 */
final class DbInvitationStore implements InvitationStoreInterface
{
    private readonly Closure $storeInvitation;
    private readonly Closure $findByTokenHash;
    private readonly Closure $listPending;
    private readonly Closure $markAccepted;
    private readonly Closure $markRevoked;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $table = (new AssertTablePrefix())->__invoke($prefix) . 'invitations';
        $repository = new Repository($connection->pdo());
        $format = (new FormatUtcTimestamp())->__invoke(...);
        $hydrate = (new HydrateInvitation((new ParseUtcTimestamp())->__invoke(...)))->__invoke(...);

        $this->storeInvitation = (new StoreInvitation($repository, $format, $table))->__invoke(...);
        $this->findByTokenHash = (new FindInvitationByTokenHash($repository, $hydrate, $table))->__invoke(...);
        $this->listPending = (new ListPendingInvitations($repository, $hydrate, $format, $table))->__invoke(...);
        $this->markAccepted = (new MarkInvitationAccepted($repository, $format, $table))->__invoke(...);
        $this->markRevoked = (new MarkInvitationRevoked($repository, $format, $table))->__invoke(...);
    }

    public function store(InvitationInterface $invitation): void
    {
        ($this->storeInvitation)($invitation);
    }

    public function findByTokenHash(string $tokenHash): ?InvitationInterface
    {
        return ($this->findByTokenHash)($tokenHash);
    }

    public function findPending(string $tenantId, string $email): ?InvitationInterface
    {
        return ($this->listPending)($tenantId, $email, 1)[0] ?? null;
    }

    public function listPendingForTenant(string $tenantId): array
    {
        return ($this->listPending)($tenantId);
    }

    public function markAccepted(string $id, DateTimeImmutable $at): bool
    {
        return ($this->markAccepted)($id, $at);
    }

    public function markRevoked(string $id, DateTimeImmutable $at): void
    {
        ($this->markRevoked)($id, $at);
    }
}
