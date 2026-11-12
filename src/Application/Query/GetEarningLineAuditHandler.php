<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Exceptions\EarningLineNotFound;
use App\Domain\Adjustment;
use App\Domain\DomainEvent;
use App\Domain\EarningLine;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\EventStore;
use DateTimeImmutable;

/**
 * Builds the audit view by replaying the line's events.
 *
 * Simplification for the PoC: the read side reuses the aggregate instead of a
 * dedicated projection. Nothing is written back to the store.
 */
final readonly class GetEarningLineAuditHandler
{
    public function __construct(private EventStore $eventStore) {}

    public function __invoke(GetEarningLineAudit $query): EarningLineAudit
    {
        $events = $this->eventStore->load($query->lineId);

        if ($events === []) {
            throw EarningLineNotFound::withId($query->lineId);
        }

        $line = EarningLine::reconstitute($events);

        return new EarningLineAudit(
            lineId: $line->getId(),
            systemValue: $line->getSystemValue(),
            adjustments: array_map(
                static fn (Adjustment $adjustment): AuditEntry => new AuditEntry(
                    $adjustment->getNumber(),
                    $adjustment->getAmount(),
                    $adjustment->getComment()->getValue(),
                    $adjustment->getOccurredAt()
                ),
                $line->getAdjustments(),
            ),
            currentValue: $line->getCurrentValue(),
            isLocked: $line->isLocked(),
            systemValueCalculatedAt: $this->getLastSystemCalculationTime($events),
        );
    }

    private function getLastSystemCalculationTime(array $events): DateTimeImmutable
    {
        $recalculateDate = array_filter($events, static fn(DomainEvent $event): bool => $event instanceof SystemValueCalculated);
        return end($recalculateDate)->getOccurredAt();
    }
}
