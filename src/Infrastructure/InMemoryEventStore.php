<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\EarningLineId;
use App\Domain\EventStore;

final class InMemoryEventStore implements EventStore
{
    public function __construct(private array $streams = []){

    }

    public function append(EarningLineId $id, array $events): void
    {
        $key = $id->getValue();
        $this->streams[$key] = [...($this->streams[$key] ?? []), ...$events];
    }

    public function load(EarningLineId $id): array
    {
        return $this->streams[$id->getValue()] ?? [];
    }
}