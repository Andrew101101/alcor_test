<?php

declare(strict_types=1);

namespace Tests\Application\Query;

use App\Application\Command\AddManualAdjustment;
use App\Application\Command\AddManualAdjustmentHandler;
use App\Application\Command\CalculateEarningLine;
use App\Application\Command\CalculateEarningLineHandler;
use App\Application\Command\RecalculateEarningLine;
use App\Application\Command\RecalculateEarningLineHandler;
use App\Application\Exceptions\EarningLineNotFound;
use App\Application\Query\AuditEntry;
use App\Application\Query\GetEarningLineAudit;
use App\Application\Query\GetEarningLineAuditHandler;
use App\Domain\Comment;
use App\Domain\EarningLineId;
use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class GetEarningLineAuditHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private GetEarningLineAuditHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->handler = new GetEarningLineAuditHandler($this->eventStore);
    }

    public function testReturnsExpectedFinalAuditHistoryFromAssignment(): void
    {
        $id = $this->lineFromAssignment();

        $audit = ($this->handler)(new GetEarningLineAudit($id));

        self::assertTrue($audit->lineId->equals($id));
        self::assertSame('$1,050.00', $audit->systemValue->getFormatted(), 'System value (frozen at step 3)');
        self::assertSame([
            [1, '-$45.55', 'Employee declined dental benefit; reversing deduction'],
            [2, '$100.10', 'Late correction: missed approved overtime bonus'],
            [3, '-$0.10', 'Minor rounding adjustment'],
            [4, '-$0.20', 'Second minor rounding adjustment'],
            [5, '$0.20', 'Correcting mistake in adjustment #4'],
        ], array_map(
            static fn (AuditEntry $e): array => [$e->number, $e->amount->getFormatted(), $e->comment],
            $audit->adjustments,
        ));
        self::assertSame('$1,104.45', $audit->currentValue->getFormatted(), 'Current (new) value');
        self::assertTrue($audit->isLocked);
    }

    public function testEveryEntryShowsWhenItWasMade(): void
    {
        $before = new \DateTimeImmutable();
        $id = $this->lineFromAssignment();
        $after = new \DateTimeImmutable();

        $audit = ($this->handler)(new GetEarningLineAudit($id));
        $previous = $before;
        foreach ($audit->adjustments as $entry) {
            self::assertGreaterThanOrEqual($previous, $entry->occurredAt, "Adjustment {$entry->number} is in chronological order");
            self::assertLessThanOrEqual($after, $entry->occurredAt);
            $previous = $entry->occurredAt;
        }
    }

    public function testShowsTimeOfLastSystemCalculationNotOfIgnoredOne(): void
    {
        $id = EarningLineId::generate();
        $this->eventStore->append($id, [
            new SystemValueCalculated($id, Money::ofCents(100000), new \DateTimeImmutable('2026-01-01 09:00')),
            new SystemValueCalculated($id, Money::ofCents(105000), new \DateTimeImmutable('2026-01-02 09:00')),
            new ManualAdjustmentAdded($id, Money::ofCents(-4555), Comment::fromString('Declined benefit'), new \DateTimeImmutable('2026-01-03 09:00')),
        ]);

        // Step 4: ignored, so it must not move the timestamp
        (new RecalculateEarningLineHandler($this->eventStore))(new RecalculateEarningLine($id, Money::ofCents(120000)));

        $audit = ($this->handler)(new GetEarningLineAudit($id));

        self::assertEquals(new \DateTimeImmutable('2026-01-02 09:00'), $audit->systemValueCalculatedAt);
        self::assertEquals(new \DateTimeImmutable('2026-01-03 09:00'), $audit->adjustments[0]->occurredAt);
    }

    public function testLineWithoutCorrectionsHasEmptyHistory(): void
    {
        $id = EarningLineId::generate();
        (new CalculateEarningLineHandler($this->eventStore))(new CalculateEarningLine($id, Money::ofCents(100000)));

        $audit = ($this->handler)(new GetEarningLineAudit($id));

        self::assertSame([], $audit->adjustments);
        self::assertSame('$1,000.00', $audit->currentValue->getFormatted());
        self::assertFalse($audit->isLocked);
    }

    public function testQueryDoesNotWriteToStore(): void
    {
        $id = $this->lineFromAssignment();
        $before = $this->eventStore->load($id);

        ($this->handler)(new GetEarningLineAudit($id));
        ($this->handler)(new GetEarningLineAudit($id));

        self::assertSame($before, $this->eventStore->load($id));
    }

    public function testRejectsUnknownLine(): void
    {
        $this->expectException(EarningLineNotFound::class);

        ($this->handler)(new GetEarningLineAudit(EarningLineId::generate()));
    }

    private function lineFromAssignment(): EarningLineId
    {
        $id = EarningLineId::generate();
        $recalculate = new RecalculateEarningLineHandler($this->eventStore);
        $adjust = new AddManualAdjustmentHandler($this->eventStore);

        (new CalculateEarningLineHandler($this->eventStore))(new CalculateEarningLine($id, Money::ofCents(100000)));
        $recalculate(new RecalculateEarningLine($id, Money::ofCents(105000)));
        $adjust(new AddManualAdjustment($id, Money::ofCents(-4555), Comment::fromString('Employee declined dental benefit; reversing deduction')));
        $recalculate(new RecalculateEarningLine($id, Money::ofCents(120000)));
        $adjust(new AddManualAdjustment($id, Money::ofCents(10010), Comment::fromString('Late correction: missed approved overtime bonus')));
        $adjust(new AddManualAdjustment($id, Money::ofCents(-10), Comment::fromString('Minor rounding adjustment')));
        $adjust(new AddManualAdjustment($id, Money::ofCents(-20), Comment::fromString('Second minor rounding adjustment')));
        $adjust(new AddManualAdjustment($id, Money::ofCents(20), Comment::fromString('Correcting mistake in adjustment #4')));

        return $id;
    }
}
