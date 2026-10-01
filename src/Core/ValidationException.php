<?php
declare(strict_types=1);

namespace Wisdom\Core;

/** Thrown when submitted form data fails validation. Carries every field error. */
final class ValidationException extends AppException
{
    /** @param array<string,string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct($errors === [] ? 'Invalid input.' : (string) reset($errors));
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
