<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

final class InvalidComment extends DomainException
{
    public static function empty(): self
    {
        return new self('Comment must not be empty');
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(sprintf('Comment must not exceed %d characters', $maxLength));
    }
}
