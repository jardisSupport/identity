<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

/** Result of an identity operation: the produced value(s) plus the domain events to dispatch. */
final readonly class AcceptResult
{
    /**
     * @param list<object> $events
     */
    public function __construct(
        public User $user,
        public Membership $membership,
        public array $events = [],
    ) {
    }
}
