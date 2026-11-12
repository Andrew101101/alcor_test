<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Comment;
use App\Domain\EarningLineId;
use App\Domain\Money;

/**
 * A payroll specialist corrects the line by a signed amount with a mandatory explanation.
 */
final readonly class AddManualAdjustment
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $amount,
        public Comment $comment,
    ) {}
}
