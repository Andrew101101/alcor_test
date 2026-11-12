<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Adjustment
{
    public function __construct(private int $number, private Money $amount, private Comment $comment, private DateTimeImmutable $occurredAt)
    {

    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}