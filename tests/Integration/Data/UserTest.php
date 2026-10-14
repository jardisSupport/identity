<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Identity\Data\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testCreateYieldsActiveUser(): void
    {
        $user = User::create('u1', 'a@b.de', 'Ann', new DateTimeImmutable('2026-01-01'));

        $this->assertSame(UserStatus::Active, $user->getStatus());
    }

    public function testWithStatusReturnsCopyAndKeepsOriginal(): void
    {
        $created = new DateTimeImmutable('2026-01-01');
        $user = User::create('u1', 'a@b.de', 'Ann', $created);

        $disabled = $user->withStatus(UserStatus::Disabled);

        $this->assertSame(UserStatus::Active, $user->getStatus());
        $this->assertSame(UserStatus::Disabled, $disabled->getStatus());
        $this->assertSame('u1', $disabled->getId());
        $this->assertSame('a@b.de', $disabled->getEmail());
        $this->assertSame('Ann', $disabled->getDisplayName());
        $this->assertSame($created, $disabled->getCreatedAt());
    }
}
