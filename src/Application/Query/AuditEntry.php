<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Money;
use DateTimeImmutable;

/**
 * One manual correction as shown in the audit history.
 */
final readonly class AuditEntry
{
    public function __construct(
        public int $number,
        public Money $amount,
        public string $comment,
        public DateTimeImmutable $occurredAt,
    ) {}
}
