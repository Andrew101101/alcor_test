<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Domain\Comment;
use App\Domain\EarningLineId;
use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

final class InMemoryEventStoreTest extends TestCase
{
    public function testUnknownStreamIsEmpty(): void
    {
        self::assertSame([], (new InMemoryEventStore())->load(EarningLineId::generate()));
    }

    public function testLoadsAppendedEventsInOrder(): void
    {
        $store = new InMemoryEventStore();
        $id = EarningLineId::generate();
        $first = new SystemValueCalculated($id, Money::ofCents(100000));
        $second = new SystemValueCalculated($id, Money::ofCents(105000));

        $store->append($id, [$first, $second]);

        self::assertSame([$first, $second], $store->load($id));
    }

    public function testAppendAddsToExistingStreamInsteadOfReplacingIt(): void
    {
        $store = new InMemoryEventStore();
        $id = EarningLineId::generate();
        $calculated = new SystemValueCalculated($id, Money::ofCents(100000));
        $adjusted = new ManualAdjustmentAdded($id, Money::ofCents(-4555), Comment::fromString('Declined benefit'));

        $store->append($id, [$calculated]);
        $store->append($id, [$adjusted]);

        self::assertSame([$calculated, $adjusted], $store->load($id));
    }

    public function testKeepsStreamsOfDifferentLinesSeparate(): void
    {
        $store = new InMemoryEventStore();
        $lineA = EarningLineId::generate();
        $lineB = EarningLineId::generate();
        $eventA = new SystemValueCalculated($lineA, Money::ofCents(100000));
        $eventB = new SystemValueCalculated($lineB, Money::ofCents(200000));

        $store->append($lineA, [$eventA]);
        $store->append($lineB, [$eventB]);

        self::assertSame([$eventA], $store->load($lineA));
        self::assertSame([$eventB], $store->load($lineB));
    }
}
