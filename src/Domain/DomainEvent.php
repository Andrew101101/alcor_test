<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface DomainEvent
{
    public function getLineId(): EarningLineId;
    public function getOccurredAt(): DateTimeImmutable;
}