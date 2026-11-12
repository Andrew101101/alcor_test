<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\Enums\Currency;
use App\Domain\Exceptions\CurrencyMismatch;
use App\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testDefaultsToUsd(): void
    {
        self::assertSame('$10.00', Money::ofCents(1000)->getFormatted());
    }

    public function testZeroHasNoAmount(): void
    {
        $zero = Money::ofCents(0, Currency::USD);

        self::assertSame(0, $zero->getAmount());
        self::assertSame(Currency::USD, $zero->getCurrency());
        self::assertSame('$0.00', $zero->getFormatted());
    }

    public function testEqualsWhenAmountAndCurrencyMatch(): void
    {
        self::assertTrue(Money::ofCents(4555)->equals(Money::ofCents(4555)));
    }

    public function testNotEqualsWhenAmountDiffers(): void
    {
        self::assertFalse(Money::ofCents(4555)->equals(Money::ofCents(4556)));
    }

    public function testNotEqualsWhenCurrencyDiffers(): void
    {
        self::assertFalse(Money::ofCents(4555, Currency::USD)->equals(Money::ofCents(4555, Currency::EUR)));
    }

    #[DataProvider('additionProvider')]
    public function testAdds(int $a, int $b, int $expected): void
    {
        self::assertSame($expected, Money::ofCents($a)->add(Money::ofCents($b))->getAmount());
    }

    public static function additionProvider(): iterable
    {
        yield 'positive'                 => [105000, 10010, 115010];
        yield 'negative'                 => [105000, -4555, 100445];
        yield 'cancels out to zero'      => [-20, 20, 0]; // steps 7-8 of the assignment
        yield 'zero is neutral'          => [4555, 0, 4555];
    }

    public function testAddReturnsNewInstanceAndKeepsOperandsUnchanged(): void
    {
        $base = Money::ofCents(105000);
        $adjustment = Money::ofCents(-4555);

        $result = $base->add($adjustment);

        self::assertNotSame($base, $result);
        self::assertSame(105000, $base->getAmount());
        self::assertSame(-4555, $adjustment->getAmount());
    }

    public function testAddKeepsCurrency(): void
    {
        $result = Money::ofCents(100, Currency::GBP)->add(Money::ofCents(50, Currency::GBP));

        self::assertSame(Currency::GBP, $result->getCurrency());
    }

    public function testCannotAddDifferentCurrencies(): void
    {
        $this->expectException(CurrencyMismatch::class);
        $this->expectExceptionMessageIs('Cannot combine USD with EUR');

        Money::ofCents(100, Currency::USD)->add(Money::ofCents(100, Currency::EUR));
    }

    #[DataProvider('formattingProvider')]
    public function testFormatsAmount(int $cents, Currency $currency, string $expected): void
    {
        self::assertSame($expected, Money::ofCents($cents, $currency)->getFormatted());
    }

    public static function formattingProvider(): iterable
    {
        // Values from the assignment's example table.
        yield 'system value'           => [105000, Currency::USD, '$1,050.00'];
        yield 'final value'            => [110445, Currency::USD, '$1,104.45'];
        yield 'negative adjustment'    => [-4555, Currency::USD, '-$45.55'];

        // Edge cases.
        yield 'single cent'            => [5, Currency::USD, '$0.05'];
        yield 'negative below 1 unit'  => [-10, Currency::USD, '-$0.10'];
        yield 'millions separator'     => [100000000, Currency::USD, '$1,000,000.00'];

        // Currency symbol comes from the enum.
        yield 'euro'                   => [4555, Currency::EUR, '€45.55'];
        yield 'pound'                  => [4555, Currency::GBP, '£45.55'];
    }
}
