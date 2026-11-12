<?php

declare(strict_types=1);

namespace Tests\Application\Command;

use App\Application\Command\CalculateEarningLine;
use App\Application\Command\CalculateEarningLineHandler;
use App\Application\Exceptions\EarningLineAlreadyExists;
use App\Domain\Comment;
use App\Domain\EarningLine;
use App\Domain\EarningLineId;
use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class CalculateEarningLineHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private CalculateEarningLineHandler $handler;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->handler = new CalculateEarningLineHandler($this->eventStore);
    }

    public function testStoresSystemValueCalculatedEventForNewLine(): void
    {
        $id = EarningLineId::generate();

        ($this->handler)(new CalculateEarningLine($id, Money::ofCents(100000)));

        $events = $this->eventStore->load($id);
        self::assertCount(1, $events);
        self::assertInstanceOf(SystemValueCalculated::class, $events[0]);
        self::assertTrue($events[0]->getLineId()->equals($id));
        self::assertSame(100000, $events[0]->getAmount()->getAmount());
    }

    public function testStoredLineCanBeRestored(): void
    {
        $id = EarningLineId::generate();

        ($this->handler)(new CalculateEarningLine($id, Money::ofCents(100000)));

        $line = EarningLine::reconstitute($this->eventStore->load($id));
        self::assertSame('$1,000.00', $line->getCurrentValue()->getFormatted());
        self::assertFalse($line->isLocked());
    }

    public function testRejectsAlreadyExistingLine(): void
    {
        $id = EarningLineId::generate();
        ($this->handler)(new CalculateEarningLine($id, Money::ofCents(100000)));

        $this->expectException(EarningLineAlreadyExists::class);
        $this->expectExceptionMessageIs(sprintf('Earning line %s already exists', $id->getValue()));

        ($this->handler)(new CalculateEarningLine($id, Money::ofCents(120000)));
    }

    /**
     * Without the existence check a second "calculate" would append a fresh
     * SystemValueCalculated after manual corrections and bypass the lock.
     */
    public function testRepeatedCalculationDoesNotOverrideLockedLine(): void
    {
        $id = EarningLineId::generate();
        ($this->handler)(new CalculateEarningLine($id, Money::ofCents(105000)));
        $this->eventStore->append($id, [
            new ManualAdjustmentAdded($id, Money::ofCents(-4555), Comment::fromString('Declined dental benefit')),
        ]);

        try {
            ($this->handler)(new CalculateEarningLine($id, Money::ofCents(120000)));
            self::fail('Expected EarningLineAlreadyExists');
        } catch (EarningLineAlreadyExists) {
        }

        self::assertCount(2, $this->eventStore->load($id), 'No event may be appended to an existing stream');
        $line = EarningLine::reconstitute($this->eventStore->load($id));
        self::assertSame('$1,004.45', $line->getCurrentValue()->getFormatted());
    }

    public function testKeepsLinesIndependent(): void
    {
        $first = EarningLineId::generate();
        $second = EarningLineId::generate();

        ($this->handler)(new CalculateEarningLine($first, Money::ofCents(100000)));
        ($this->handler)(new CalculateEarningLine($second, Money::ofCents(200000)));

        self::assertCount(1, $this->eventStore->load($first));
        self::assertCount(1, $this->eventStore->load($second));
    }
}
