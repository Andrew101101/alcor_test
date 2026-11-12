<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exceptions\InvalidComment;

/**
 * Mandatory explanation attached to a manual correction.
 */
final readonly class Comment
{
    private const int MAX_LENGTH = 500;

    /**
     * @throws InvalidComment
     */
    private function __construct(private string $value)
    {
        if ($value === '') {
            throw InvalidComment::empty();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidComment::tooLong(self::MAX_LENGTH);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
