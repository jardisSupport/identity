<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/**
 * Lists pending invitations of a tenant (optionally for one email):
 * not accepted, not revoked, not expired (expires_at > now, UTC).
 */
final class ListPendingInvitations
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    /** @return list<InvitationInterface> */
    public function __invoke(string $tenantId, ?string $email = null, ?int $limit = null): array
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('tenant_id')->equals($tenantId)
            ->and('accepted_at')->isNull()
            ->and('revoked_at')->isNull()
            ->and('expires_at')->greater(($this->formatTimestamp)(new DateTimeImmutable('now')));
        if ($email !== null) {
            $query->and('email')->equals($email);
        }
        $query->orderBy('created_at', 'DESC')->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        return array_values(array_map(
            fn (array $row): InvitationInterface => ($this->hydrate)($row),
            $this->repository->findByQuery($query),
        ));
    }
}
