<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\User;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** UserStoreInterface behaviour of DbUserStore against a real database (one subclass per driver). */
abstract class DbUserStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbUserStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbUserStore($this->connection);
    }

    private function user(string $id = 'u-1', string $email = 'ada@example.com'): User
    {
        return User::create($id, $email, 'Ada Lovelace', Moment::fixed());
    }

    // (1) roundtrip of all fields
    public function testStoreAndFindRoundTripsAllFields(): void
    {
        $this->store->store($this->user(), 'hash-1');

        foreach ([$this->store->findById('u-1'), $this->store->findByEmail('ada@example.com')] as $found) {
            self::assertNotNull($found);
            self::assertSame('u-1', $found->getId());
            self::assertSame('ada@example.com', $found->getEmail());
            self::assertSame('Ada Lovelace', $found->getDisplayName());
            self::assertSame(UserStatus::Active, $found->getStatus());
            self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getCreatedAt()));
        }
        self::assertSame('hash-1', $this->store->passwordHashOf('u-1'));
    }

    public function testUpdateStatusAndPasswordHash(): void
    {
        $this->store->store($this->user(), 'hash-1');

        $this->store->updateStatus('u-1', UserStatus::Disabled);
        $this->store->updatePasswordHash('u-1', 'hash-2');

        self::assertSame(UserStatus::Disabled, $this->store->findById('u-1')?->getStatus());
        self::assertSame('hash-2', $this->store->passwordHashOf('u-1'));
    }

    public function testFindByEmailIsAnExactMatch(): void
    {
        $this->store->store($this->user(), 'hash-1');

        self::assertNull($this->store->findByEmail('ada@example.org'));
        self::assertNull($this->store->findByEmail('ada@example.co'));
    }

    // (2) unknown -> null
    public function testUnknownReturnsNull(): void
    {
        self::assertNull($this->store->findById('missing'));
        self::assertNull($this->store->findByEmail('nobody@example.com'));
        self::assertNull($this->store->passwordHashOf('missing'));
    }

    public function testUpdatesOfUnknownIdAreNoOps(): void
    {
        $this->store->updateStatus('missing', UserStatus::Disabled);
        $this->store->updatePasswordHash('missing', 'x');

        self::assertNull($this->store->findById('missing'));
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateEmailThrowsUniqueViolation(): void
    {
        $this->store->store($this->user('u-1'), 'hash-1');

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->user('u-2'), 'hash-2');
    }

    public function testDuplicateIdThrowsUniqueViolation(): void
    {
        $this->store->store($this->user('u-1', 'a@example.com'), 'hash-1');

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->user('u-1', 'b@example.com'), 'hash-2');
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbUserStore($this->connection, 'acme_');

        $store->store($this->user(), 'hash-1');

        self::assertNotNull($store->findById('u-1'));
        self::assertNull($this->store->findById('u-1'));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbUserStore($this->connection, 'identity_; DROP TABLE x');
    }
}
