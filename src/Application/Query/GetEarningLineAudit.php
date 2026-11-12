<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\EarningLineId;

/**
 * Asks for the line's current value and the full history of corrections that produced it.
 */
final readonly class GetEarningLineAudit
{
    public function __construct(
        public EarningLineId $lineId,
    ) {}
}
