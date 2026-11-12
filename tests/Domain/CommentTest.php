<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Comment;
use App\Domain\Exceptions\InvalidComment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommentTest extends TestCase
{
    public function testKeepsValidText(): void
    {
        $text = 'Employee declined dental benefit; reversing deduction';

        self::assertSame($text, Comment::fromString($text)->getValue());
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        self::assertSame('Late correction', Comment::fromString("  Late correction \n")->getValue());
    }

    #[DataProvider('blankProvider')]
    public function testRejectsBlankText(string $blank): void
    {
        $this->expectException(InvalidComment::class);
        $this->expectExceptionMessageIs('Comment must not be empty');

        Comment::fromString($blank);
    }

    public static function blankProvider(): iterable
    {
        yield 'empty'      => [''];
        yield 'spaces'     => ['   '];
        yield 'tabs/lines' => ["\t\n\r "];
    }

    public function testAcceptsTextAtMaxLength(): void
    {
        self::assertSame(500, mb_strlen(Comment::fromString(str_repeat('a', 500))->getValue()));
    }

    public function testRejectsTextOverMaxLength(): void
    {
        $this->expectException(InvalidComment::class);
        $this->expectExceptionMessageIs('Comment must not exceed 500 characters');

        Comment::fromString(str_repeat('a', 501));
    }

    public function testCountsMultibyteCharactersNotBytes(): void
    {
        self::assertSame(500, mb_strlen(Comment::fromString(str_repeat('я', 500))->getValue()));
    }
}
