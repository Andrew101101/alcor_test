<?php

declare(strict_types=1);

namespace App\Domain;

interface EventStore
{
    /** @param list<DomainEvent> $events */
    public function append(EarningLineId $id, array $events): void;

    /** @return list<DomainEvent> */
    public function load(EarningLineId $id): array;

}