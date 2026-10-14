<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

/** Result of an identity operation: the produced value(s) plus the domain events to dispatch. */
final readonly class GroupResult
{
    /**
     * @param list<object> $events
     */
    public function __construct(
        public Group $group,
        public array $events = [],
    ) {
    }
}
