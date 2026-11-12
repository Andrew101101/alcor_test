<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Exceptions\EarningLineNotFound;
use App\Domain\EarningLine;
use App\Domain\EventStore;

final readonly class RecalculateEarningLineHandler
{
    public function __construct(private EventStore $eventStore) {}

    public function __invoke(RecalculateEarningLine $command): void
    {
        $events = $this->eventStore->load($command->lineId);

        if ($events === []) {
            throw EarningLineNotFound::withId($command->lineId);
        }

        $line = EarningLine::reconstitute($events);
        $line->recalculate($command->amount);

        // Empty when the line is locked or the value did not change.
        $this->eventStore->append($line->getId(), $line->releaseEvents());
    }
}
