<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use Exception;

/**
 * Base for use-case level errors (missing or duplicate lines),
 * as opposed to domain rule violations in App\Domain\Exceptions.
 */
abstract class ApplicationException extends Exception
{
}
