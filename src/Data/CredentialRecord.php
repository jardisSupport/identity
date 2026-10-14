<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use JardisSupport\Contract\Auth\CredentialRecordInterface;

/** Login record handed to the authenticator: password hash, qualified subject and session claims. */
final readonly class CredentialRecord implements CredentialRecordInterface
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public string $passwordHash,
        public string $subject,
        public array $claims = [],
    ) {
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClaims(): array
    {
        return $this->claims;
    }
}
