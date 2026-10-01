<?php
declare(strict_types=1);

namespace Wisdom\Core;

use RuntimeException;

/**
 * An error whose message is safe to show to the end user
 * (e.g. "That email is already registered.").
 * Anything that is NOT an AppException is treated as an internal error.
 */
class AppException extends RuntimeException
{
}
