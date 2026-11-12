<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Enums\Currency;
use App\Domain\Exceptions\CurrencyMismatch;

final readonly class Money
{
    private function __construct(private int $amount, private Currency $currency) { }

    public static function ofCents(int $cents, Currency $currency = Currency::USD): self
    {
        return new self($cents, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * @throws CurrencyMismatch
     */
    public function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatch::between($this->currency, $other->currency);
        }
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    /**
     * Formats as "$1,104.45" / "-$45.55".
     */
    public function getFormatted(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $absolute = abs($this->amount);

        return sprintf(
            '%s%s%s.%02d',
            $sign,
            $this->currency->symbol(),
            number_format(intdiv($absolute, 100)),
            $absolute % 100,
        );
    }

    public function equals(Money $money): bool
    {
        return $money->getAmount() === $this->getAmount() && $money->getCurrency() === $this->getCurrency();
    }
}