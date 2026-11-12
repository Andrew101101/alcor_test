<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Events\ManualAdjustmentAdded;
use App\Domain\Events\SystemValueCalculated;
use App\Domain\Exceptions\InvalidAdjustment;
use App\Domain\Exceptions\InvalidEventStream;

final class EarningLine
{
    private EarningLineId $id;
    private Money $systemValue;

    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @var list<Adjustment> */
    private array $adjustments = [];

    private function __construct()
    {

    }

    public function getId(): EarningLineId
    {
       return $this->id;
    }

    public static function calculate(EarningLineId $id, Money $money): EarningLine
    {
        $line = new self();
        $line->recordThat(new SystemValueCalculated($id, $money));

        return $line;
    }

    public function addAdjustment(Money $amount, Comment $comment): void
    {
        if ($amount->getAmount() === 0) {
            throw InvalidAdjustment::zero();
        }

        $this->systemValue->assertSameCurrency($amount);

        $this->recordThat(new ManualAdjustmentAdded($this->id, $amount, $comment));
    }

    public function recalculate(Money $amount): void
    {
        $this->systemValue->assertSameCurrency($amount);

        if ($this->isLocked()) {
            return;
        }

        if ($amount->equals($this->systemValue)) {
            return;
        }

        $this->recordThat(new SystemValueCalculated($this->id, $amount));
    }

    public function isLocked(): bool
    {
        return $this->adjustments !== [];
    }

    public function getCurrentValue(): Money
    {
        $total = $this->systemValue;

        foreach ($this->adjustments as $adjustment) {
            $total = $total->add($adjustment->getAmount());
        }

        return $total;
    }

    public function getSystemValue(): Money
    {
        return $this->systemValue;
    }

    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    public static function reconstitute(array $events): EarningLine
    {
        if ($events === []) {
            throw InvalidEventStream::empty();
        }

        $line = new self();

        foreach ($events as $event) {
            $line->apply($event);
        }

        return $line;

    }

    public function getAdjustments(): array
    {
        return $this->adjustments;
    }

    private function recordThat(DomainEvent $event): void
    {
        $this->apply($event);
        $this->recordedEvents[] = $event;
    }

    private function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof SystemValueCalculated => $this->applySystemValueCalculated($event),
            $event instanceof ManualAdjustmentAdded => $this->applyManualAdjustmentAdded($event),
        };
    }

    private function applySystemValueCalculated(SystemValueCalculated $event): void
    {
        $this->id = $event->getLineId();
        $this->systemValue = $event->getAmount();
    }

    private function applyManualAdjustmentAdded(ManualAdjustmentAdded $event): void
    {
        $this->adjustments[] = new Adjustment(
            count($this->adjustments) + 1,
            $event->getAmount(),
            $event->getComment(),
            $event->getOccurredAt()
        );
    }
}