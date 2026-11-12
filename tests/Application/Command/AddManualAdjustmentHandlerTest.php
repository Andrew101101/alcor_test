<?php

declare(strict_types=1);

namespace Tests\Application\Command;

use App\Application\Command\AddManualAdjustment;
use App\Application\Command\AddManualAdjustmentHandler;
use App\Application\Command\CalculateEarningLine;
use App\Application\Command\CalculateEarningLineHandler;
use App\Application\Exceptions\EarningLineNotFound;
use App\Domain\Comment;
use App\Domain\EarningLine;
use App\Domain\EarningLineId;
use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Exceptions\InvalidAdjustment;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class AddManualAdjustmentHandlerTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private AddManualAdjustmentHandler $handler;
    private EarningLineId $id;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->handler = new AddManualAdjustmentHandler($this->eventStore);

        $this->id = EarningLineId::generate();
        (new CalculateEarningLineHandler($this->eventStore))(new CalculateEarningLine($this->id, Money::ofCents(105000)));
    }

    public function testAppendsAdjustmentToExistingStream(): void
    {
        ($this->handler)(new AddManualAdjustment($this->id, Money::ofCents(-4555), Comment::fromString('Declined dental benefit')));

        $events = $this->eventStore->load($this->id);
        self::assertCount(2, $events);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[1]);

        $line = EarningLine::reconstitute($events);
        self::assertSame('$1,004.45', $line->getCurrentValue()->getFormatted());
        self::assertTrue($line->isLocked());
    }

    public function testKeepsEveryAdjustmentAcrossSeparateCommands(): void
    {
        ($this->handler)(new AddManualAdjustment($this->id, Money::ofCents(-20), Comment::fromString('Rounding')));
        ($this->handler)(new AddManualAdjustment($this->id, Money::ofCents(20), Comment::fromString('Correcting mistake in adjustment #1')));

        $line = EarningLine::reconstitute($this->eventStore->load($this->id));
        self::assertCount(2, $line->getAdjustments());
        self::assertSame(2, $line->getAdjustments()[1]->getNumber());
        self::assertSame('$1,050.00', $line->getCurrentValue()->getFormatted());
    }

    public function testRejectedAdjustmentIsNotStored(): void
    {
        try {
            ($this->handler)(new AddManualAdjustment($this->id, Money::ofCents(0), Comment::fromString('Nothing')));
            self::fail('Expected InvalidAdjustment');
        } catch (InvalidAdjustment) {
        }

        self::assertCount(1, $this->eventStore->load($this->id));
    }

    public function testRejectsUnknownLine(): void
    {
        $unknown = EarningLineId::generate();

        $this->expectException(EarningLineNotFound::class);

        ($this->handler)(new AddManualAdjustment($unknown, Money::ofCents(100), Comment::fromString('Bonus')));
    }
}
