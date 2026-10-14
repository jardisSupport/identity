<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Identity\Handler\Shared\ParseUtcTimestamp;
use JardisSupport\Identity\Handler\UserStore\FindPasswordHash;
use JardisSupport\Identity\Handler\UserStore\FindUserByEmail;
use JardisSupport\Identity\Handler\UserStore\FindUserById;
use JardisSupport\Identity\Handler\UserStore\HydrateUser;
use JardisSupport\Identity\Handler\UserStore\StoreUser;
use JardisSupport\Identity\Handler\UserStore\UpdateUserPasswordHash;
use JardisSupport\Identity\Handler\UserStore\UpdateUserStatus;
use JardisSupport\Repository\Repository;

/**
 * Persists users on a database connection via jardissupport/repository.
 *
 * Table DDL ships in resources/sql/<driver>.sql; the table is "<prefix>users".
 */
final class DbUserStore implements UserStoreInterface
{
    private readonly Closure $storeUser;
    private readonly Closure $updateStatus;
    private readonly Closure $updatePasswordHash;
    private readonly Closure $findById;
    private readonly Closure $findByEmail;
    private readonly Closure $findPasswordHash;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $table = (new AssertTablePrefix())->__invoke($prefix) . 'users';
        $repository = new Repository($connection->pdo());
        $format = (new FormatUtcTimestamp())->__invoke(...);
        $hydrate = (new HydrateUser((new ParseUtcTimestamp())->__invoke(...)))->__invoke(...);

        $this->storeUser = (new StoreUser($repository, $format, $table))->__invoke(...);
        $this->updateStatus = (new UpdateUserStatus($repository, $table))->__invoke(...);
        $this->updatePasswordHash = (new UpdateUserPasswordHash($repository, $table))->__invoke(...);
        $this->findById = (new FindUserById($repository, $hydrate, $table))->__invoke(...);
        $this->findByEmail = (new FindUserByEmail($repository, $hydrate, $table))->__invoke(...);
        $this->findPasswordHash = (new FindPasswordHash($repository, $table))->__invoke(...);
    }

    public function store(UserInterface $user, string $passwordHash): void
    {
        ($this->storeUser)($user, $passwordHash);
    }

    public function updateStatus(string $id, UserStatus $status): void
    {
        ($this->updateStatus)($id, $status);
    }

    public function updatePasswordHash(string $id, string $hash): void
    {
        ($this->updatePasswordHash)($id, $hash);
    }

    public function findById(string $id): ?UserInterface
    {
        return ($this->findById)($id);
    }

    public function findByEmail(string $email): ?UserInterface
    {
        return ($this->findByEmail)($email);
    }

    public function passwordHashOf(string $id): ?string
    {
        return ($this->findPasswordHash)($id);
    }
}
