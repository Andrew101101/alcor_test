<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class InvalidEventStream extends DomainException
{
    public static function empty(): self
    {
        return new self('Cannot reconstitute EarningLine from an empty event stream');
    }
}