<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Group;

use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\Contract\Identity\GroupStoreInterface;
use JardisSupport\Identity\Exception\GroupNotFoundException;

/** Loads a group by id or fails with GroupNotFoundException. */
final class LoadGroup
{
    public function __construct(private readonly GroupStoreInterface $groups)
    {
    }

    public function __invoke(string $groupId): GroupInterface
    {
        return $this->groups->findById($groupId)
            ?? throw new GroupNotFoundException(sprintf('Group "%s" not found', $groupId));
    }
}
