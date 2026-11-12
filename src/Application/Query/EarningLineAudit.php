<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\EarningLineId;
use App\Domain\Money;
use DateTimeImmutable;

/**
 * Read-only view of an earning line: system value, corrections and the resulting value.
 * Returned instead of the aggregate so readers cannot issue commands on it.
 */
final readonly class EarningLineAudit
{
    /**
     * @param list<AuditEntry> $adjustments
     */
    public function __construct(
        public EarningLineId $lineId,
        public Money $systemValue,
        public array $adjustments,
        public Money $currentValue,
        public bool $isLocked,
        public DateTimeImmutable $systemValueCalculatedAt,
    ) {}
}
