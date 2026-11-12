<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class InvalidAdjustment extends DomainException
{
    public static function zero(): self
    {
        return new self('Adjustment amount must not be zero');
    }
}