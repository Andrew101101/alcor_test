<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\Enums\Currency;

final class CurrencyMismatch extends DomainException
{
    public static function between(Currency $a, Currency $b): self
    {
        return new self(sprintf('Cannot combine %s with %s', $a->value, $b->value));
    }
}