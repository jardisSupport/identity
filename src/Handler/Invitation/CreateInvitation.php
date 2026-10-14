<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Invitation;

use Closure;
use DateInterval;
use DateTimeImmutable;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Contract\Auth\TokenStoreInterface;
use JardisSupport\Contract\Auth\TokenType;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\InvitationStoreInterface;
use JardisSupport\Identity\Data\Event\InvitationCreated;
use JardisSupport\Identity\Data\Invitation;
use JardisSupport\Identity\Data\InvitationResult;

/**
 * Invites an e-mail address into a tenant and issues the one-time token.
 *
 * An open invitation for the same (tenant, e-mail) pair is revoked via markRevoked, and its auth
 * token is revoked in the token store, so redeeming the old plain token fails at the token already.
 */
final class CreateInvitation
{
    /**
     * @param Closure(string): string $normalizeEmail
     * @param Closure(string): string $assertEmail
     * @param positive-int $ttl
     */
    public function __construct(
        private readonly InvitationStoreInterface $invitations,
        private readonly IdentityInterface $ids,
        private readonly TokenIssuer $issuer,
        private readonly TokenStoreInterface $tokens,
        private readonly Closure $normalizeEmail,
        private readonly Closure $assertEmail,
        private readonly int $ttl,
    ) {
    }

    public function __invoke(string $tenantId, string $email, string $role): InvitationResult
    {
        $normalized = ($this->assertEmail)(($this->normalizeEmail)($email));
        $now = new DateTimeImmutable();

        $open = $this->invitations->findPending($tenantId, $normalized);
        if ($open !== null) {
            $this->invitations->markRevoked($open->getId(), $now);
            $this->tokens->revoke($open->getTokenHash());
        }

        $id = $this->ids->generateUuid7();
        $issued = $this->issuer->issue(
            TokenType::Verification,
            Subject::from($id, 'invitation'),
            $this->ttl,
            ['tenant' => $tenantId, 'email' => $normalized, 'role' => $role],
        );

        $invitation = Invitation::create(
            $id,
            $tenantId,
            $normalized,
            $role,
            $issued->hashed->hash,
            $now->add(new DateInterval('PT' . $this->ttl . 'S')),
            $now,
        );
        $this->invitations->store($invitation);

        return new InvitationResult(
            $invitation,
            $issued->plain,
            [new InvitationCreated($id, $tenantId, $normalized, $role, $now)],
        );
    }
}
