<?php

declare(strict_types=1);

namespace Tests\Application\Command;

use App\Application\Command\AddManualAdjustment;
use App\Application\Command\AddManualAdjustmentHandler;
use App\Application\Command\CalculateEarningLine;
use App\Application\Command\CalculateEarningLineHandler;
use App\Application\Command\RecalculateEarningLine;
use App\Application\Command\RecalculateEarningLineHandler;
use App\Application\Exceptions\EarningLineNotFound;
use App\Domain\Comment;
use App\Domain\EarningLine;
use App\Domain\EarningLineId;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class RecalculateEarningLineHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private RecalculateEarningLineHandler $handler;
    private EarningLineId $id;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->handler = new RecalculateEarningLineHandler($this->eventStore);

        $this->id = EarningLineId::generate();
        (new CalculateEarningLineHandler($this->eventStore))(new CalculateEarningLine($this->id, Money::ofCents(100000)));
    }

    public function testUpdatesSystemValueOfUnlockedLine(): void
    {
        ($this->handler)(new RecalculateEarningLine($this->id, Money::ofCents(105000)));

        self::assertCount(2, $this->eventStore->load($this->id));
        self::assertSame('$1,050.00', $this->restore()->getSystemValue()->getFormatted());
    }

    public function testIgnoresRecalculationOfLockedLine(): void
    {
        (new AddManualAdjustmentHandler($this->eventStore))(
            new AddManualAdjustment($this->id, Money::ofCents(-4555), Comment::fromString('Declined dental benefit')),
        );

        ($this->handler)(new RecalculateEarningLine($this->id, Money::ofCents(120000)));

        self::assertCount(2, $this->eventStore->load($this->id), 'Ignored recalculation must not be stored');
        self::assertSame('$954.45', $this->restore()->getCurrentValue()->getFormatted());
    }

    public function testStoresNothingWhenValueIsUnchanged(): void
    {
        ($this->handler)(new RecalculateEarningLine($this->id, Money::ofCents(100000)));

        self::assertCount(1, $this->eventStore->load($this->id));
    }

    public function testRejectsUnknownLine(): void
    {
        $unknown = EarningLineId::generate();

        $this->expectException(EarningLineNotFound::class);
        $this->expectExceptionMessageIs(sprintf('Earning line %s not found', $unknown->getValue()));

        ($this->handler)(new RecalculateEarningLine($unknown, Money::ofCents(100000)));
    }

    private function restore(): EarningLine
    {
        return EarningLine::reconstitute($this->eventStore->load($this->id));
    }
}
