<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Adjustment;
use App\Domain\Comment;
use App\Domain\EarningLine;
use App\Domain\EarningLineId;
use App\Domain\Enums\Currency;
use App\Domain\Exceptions\CurrencyMismatch;
use App\Domain\Exceptions\InvalidEventStream;
use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\Exceptions\InvalidAdjustment;
use App\Domain\Money;
use PHPUnit\Framework\TestCase;

/**
 * Scenario from the assignment's "examples and expected numbers" table.
 */
final class EarningLineTest extends TestCase
{
    public function testAssignmentScenarioStepByStep(): void
    {
        // Step 1: system calculates the line
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        self::assertSame(100000, $line->getCurrentValue()->getAmount());

        // Step 2: source data changes, recalculation is allowed
        $line->recalculate(Money::ofCents(105000));
        self::assertSame(105000, $line->getCurrentValue()->getAmount());

        // Step 3: first manual correction locks the line
        $line->addAdjustment(Money::ofCents(-4555), Comment::fromString('Employee declined dental benefit; reversing deduction'));
        self::assertSame(100445, $line->getCurrentValue()->getAmount());

        // Step 4: recalculation must be ignored
        $line->recalculate(Money::ofCents(120000));
        self::assertSame(100445, $line->getCurrentValue()->getAmount());

        // Steps 5-8: further corrections, including a compensating one
        $line->addAdjustment(Money::ofCents(10010), Comment::fromString('Late correction: missed approved overtime bonus'));
        self::assertSame(110455, $line->getCurrentValue()->getAmount());

        $line->addAdjustment(Money::ofCents(-10), Comment::fromString('Minor rounding adjustment'));
        self::assertSame(110445, $line->getCurrentValue()->getAmount());

        $line->addAdjustment(Money::ofCents(-20), Comment::fromString('Second minor rounding adjustment'));
        self::assertSame(110425, $line->getCurrentValue()->getAmount());

        $line->addAdjustment(Money::ofCents(20), Comment::fromString('Correcting mistake in adjustment #4'));
        self::assertSame(110445, $line->getCurrentValue()->getAmount());
    }

    public function testFinalAuditHistory(): void
    {
        $line = $this->lineFromAssignment();

        self::assertSame('$1,050.00', $line->getSystemValue()->getFormatted(), 'System value is frozen at step 3');

        $history = array_map(
            static fn (Adjustment $a): array => [$a->getNumber(), $a->getAmount()->getFormatted()],
            $line->getAdjustments(),
        );
        self::assertSame([
            [1, '-$45.55'],
            [2, '$100.10'],
            [3, '-$0.10'],
            [4, '-$0.20'],
            [5, '$0.20'],
        ], $history);

        self::assertSame('$1,104.45', $line->getCurrentValue()->getFormatted());
    }

    public function testAdjustmentKeepsItsComment(): void
    {
        $line = $this->lineFromAssignment();

        self::assertSame('Correcting mistake in adjustment #4', $line->getAdjustments()[4]->getComment()->getValue());
    }

    public function testIsNotLockedUntilFirstAdjustment(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        self::assertFalse($line->isLocked());

        $line->addAdjustment(Money::ofCents(100), Comment::fromString('Bonus'));
        self::assertTrue($line->isLocked());
    }

    public function testRecordsEventsForEveryChange(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        $line->recalculate(Money::ofCents(105000));
        $line->addAdjustment(Money::ofCents(-4555), Comment::fromString('Declined benefit'));

        $events = $line->releaseEvents();

        self::assertCount(3, $events);
        self::assertInstanceOf(SystemValueCalculated::class, $events[0]);
        self::assertInstanceOf(SystemValueCalculated::class, $events[1]);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[2]);
    }

    public function testReleaseEventsClearsRecordedEvents(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        $line->releaseEvents();

        self::assertSame([], $line->releaseEvents());
    }

    public function testIgnoredRecalculationRecordsNoEvent(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        $line->addAdjustment(Money::ofCents(100), Comment::fromString('Bonus'));
        $line->releaseEvents();

        $line->recalculate(Money::ofCents(200000));

        self::assertSame([], $line->releaseEvents());
    }

    public function testRecalculationWithSameValueRecordsNoEvent(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        $line->releaseEvents();

        $line->recalculate(Money::ofCents(100000));

        self::assertSame([], $line->releaseEvents());
    }

    public function testRejectsZeroAdjustment(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));

        $this->expectException(InvalidAdjustment::class);

        $line->addAdjustment(Money::ofCents(0), Comment::fromString('Nothing'));
    }

    public function testRejectsAdjustmentInDifferentCurrency(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000, Currency::USD));

        $this->expectException(CurrencyMismatch::class);

        $line->addAdjustment(Money::ofCents(100, Currency::EUR), Comment::fromString('Bonus'));
    }

    public function testRejectsRecalculationInDifferentCurrencyEvenWhenLocked(): void
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000, Currency::USD));
        $line->addAdjustment(Money::ofCents(100), Comment::fromString('Bonus'));

        $this->expectException(CurrencyMismatch::class);

        $line->recalculate(Money::ofCents(100000, Currency::EUR));
    }

    public function testReconstitutesSameStateFromEvents(): void
    {
        $original = $this->lineFromAssignment();

        $restored = EarningLine::reconstitute($original->releaseEvents());

        self::assertTrue($restored->getCurrentValue()->equals($original->getCurrentValue()));
        self::assertTrue($restored->getSystemValue()->equals($original->getSystemValue()));
        self::assertCount(5, $restored->getAdjustments());
        self::assertTrue($restored->isLocked());
        self::assertSame([], $restored->releaseEvents(), 'Replaying history must not record new events');
    }

    public function testReplayKeepsOriginalTimeOfAdjustment(): void
    {
        $id = EarningLineId::generate();
        $madeAt = new \DateTimeImmutable('2026-01-15 10:30:00');

        $line = EarningLine::reconstitute([
            new SystemValueCalculated($id, Money::ofCents(105000), new \DateTimeImmutable('2026-01-01 00:00:00')),
            new ManualAdjustmentAdded($id, Money::ofCents(-4555), Comment::fromString('Declined benefit'), $madeAt),
        ]);

        self::assertEquals($madeAt, $line->getAdjustments()[0]->getOccurredAt(), 'Time comes from the event, not from the moment of loading');
    }

    public function testCannotReconstituteFromEmptyStream(): void
    {
        $this->expectException(InvalidEventStream::class);

        EarningLine::reconstitute([]);
    }

    private function lineFromAssignment(): EarningLine
    {
        $line = EarningLine::calculate(EarningLineId::generate(), Money::ofCents(100000));
        $line->recalculate(Money::ofCents(105000));
        $line->addAdjustment(Money::ofCents(-4555), Comment::fromString('Employee declined dental benefit; reversing deduction'));
        $line->recalculate(Money::ofCents(120000));
        $line->addAdjustment(Money::ofCents(10010), Comment::fromString('Late correction: missed approved overtime bonus'));
        $line->addAdjustment(Money::ofCents(-10), Comment::fromString('Minor rounding adjustment'));
        $line->addAdjustment(Money::ofCents(-20), Comment::fromString('Second minor rounding adjustment'));
        $line->addAdjustment(Money::ofCents(20), Comment::fromString('Correcting mistake in adjustment #4'));

        return $line;
    }
}
