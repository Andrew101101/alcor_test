<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\EarningLineId;
use App\Domain\Money;

/**
 * Source data changed and the system produced a new value for an existing line.
 */
final readonly class RecalculateEarningLine
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $amount,
    ) {}
}
