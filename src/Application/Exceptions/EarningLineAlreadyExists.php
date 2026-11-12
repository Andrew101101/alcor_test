<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use App\Domain\EarningLineId;

final class EarningLineAlreadyExists extends ApplicationException
{
    public static function withId(EarningLineId $id): self
    {
        return new self(sprintf('Earning line %s already exists', $id->getValue()));
    }
}
