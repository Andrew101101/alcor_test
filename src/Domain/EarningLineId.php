<?php

declare(strict_types=1);

namespace App\Domain;

use Ramsey\Uuid\Uuid;

final readonly class EarningLineId
{
    private function __construct(private string $id)
    {

    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function getValue(): string
    {
        return $this->id;
    }

    public function equals(EarningLineId $otherLineId): bool
    {
        return $this->getValue() === $otherLineId->getValue();
    }
}