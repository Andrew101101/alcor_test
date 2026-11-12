<?php

declare(strict_types=1);

namespace App\Domain\Events;

use App\Domain\DomainEvent;
use App\Domain\EarningLineId;
use App\Domain\Money;
use DateTimeImmutable;

final readonly class SystemValueCalculated implements DomainEvent
{
    public function __construct(
        private EarningLineId $earningLineId,
        private Money         $amount,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable()
    ) { }

    public function getLineId(): EarningLineId
    {
        return $this->earningLineId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}