<?php

declare(strict_types=1);

namespace Tests\Domain;

use App\Domain\EarningLineId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class EarningLineIdTest extends TestCase
{
    public function testGeneratesValidUuid(): void
    {
        self::assertTrue(Uuid::isValid(EarningLineId::generate()->getValue()));
    }

    public function testGeneratesUniqueIds(): void
    {
        self::assertFalse(EarningLineId::generate()->equals(EarningLineId::generate()));
    }

    public function testEqualsItself(): void
    {
        $id = EarningLineId::generate();

        self::assertTrue($id->equals($id));
    }
}
