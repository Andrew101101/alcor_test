<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Exceptions\EarningLineAlreadyExists;
use App\Domain\EarningLine;
use App\Domain\EventStore;

final class CalculateEarningLineHandler
{
    public function __construct(private EventStore $eventStore) {}

    public function __invoke(CalculateEarningLine $command): void
    {
        if ($this->eventStore->load($command->lineId) !== []) {
            throw EarningLineAlreadyExists::withId($command->lineId);
        }

        $line = EarningLine::calculate($command->lineId, $command->amount);

        $this->eventStore->append($line->getId(), $line->releaseEvents());
    }

}