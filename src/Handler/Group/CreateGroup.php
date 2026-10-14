<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Group;

use DateTimeImmutable;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\GroupStoreInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Event\GroupCreated;
use JardisSupport\Identity\Data\Group;
use JardisSupport\Identity\Data\GroupResult;
use JardisSupport\Identity\Exception\GroupNameTakenException;

/** Creates a group in a tenant; the name is unique per tenant. */
final class CreateGroup
{
    public function __construct(
        private readonly GroupStoreInterface $groups,
        private readonly IdentityInterface $ids,
    ) {
    }

    public function __invoke(string $tenantId, string $name): GroupResult
    {
        $now = new DateTimeImmutable();
        $group = new Group($this->ids->generateUuid7(), $tenantId, $name, $now);

        try {
            $this->groups->store($group);
        } catch (UniqueViolationException $e) {
            throw new GroupNameTakenException(sprintf('Group "%s" already exists in the tenant', $name), 0, $e);
        }

        return new GroupResult($group, [new GroupCreated($group->getId(), $tenantId, $name, $now)]);
    }
}
