<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\EarningLineId;
use App\Domain\Money;

final readonly class CalculateEarningLine
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $amount,
    ) {}
}
