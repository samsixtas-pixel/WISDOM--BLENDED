<?php
declare(strict_types=1);

namespace Wisdom\Core;

/** Chainable server-side validator. Collects one error per field. */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === [] || (is_string($value) && trim($value) === '')) {
            $this->fail($field, $label . ' is required.');
        }

        return $this;
    }

    public function length(string $field, string $label, int $min, int $max): self
    {
        $value = (string) ($this->data[$field] ?? '');
        $length = str_len($value);
        if ($value !== '' && ($length < $min || $length > $max)) {
            $this->fail($field, sprintf('%s must be between %d and %d characters.', $label, $min, $max));
        }

        return $this;
    }

    public function email(string $field, string $label = 'Email'): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && (strlen($value) > 254 || !filter_var($value, FILTER_VALIDATE_EMAIL))) {
            $this->fail($field, 'Enter a valid ' . strtolower($label) . ' address.');
        }

        return $this;
    }

    /** @param list<string> $allowed */
    public function in(string $field, string $label, array $allowed): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->fail($field, 'Choose a valid ' . strtolower($label) . '.');
        }

        return $this;
    }

    public function pattern(string $field, string $label, string $regex, string $message = ''): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && preg_match($regex, $value) !== 1) {
            $this->fail($field, $message !== '' ? $message : $label . ' contains invalid characters.');
        }

        return $this;
    }

    public function numeric(string $field, string $label, ?float $min = null, ?float $max = null): self
    {
        $value = $this->data[$field] ?? '';
        if ($value === '' || $value === null) {
            return $this;
        }
        if (!is_numeric($value)) {
            $this->fail($field, $label . ' must be a number.');
        } elseif (($min !== null && (float) $value < $min) || ($max !== null && (float) $value > $max)) {
            $this->fail($field, sprintf('%s must be between %s and %s.', $label, $min ?? '-∞', $max ?? '∞'));
        }

        return $this;
    }

    public function matches(string $field, string $otherField, string $message): self
    {
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->fail($field, $message);
        }

        return $this;
    }

    /** Minimum length, at least one letter and one digit, and bcrypt's 72-byte ceiling. */
    public function password(string $field, int $minLength, string $label = 'Password'): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value === '') {
            return $this;
        }
        if (strlen($value) < $minLength) {
            $this->fail($field, sprintf('%s must be at least %d characters.', $label, $minLength));
        } elseif (strlen($value) > 72) {
            $this->fail($field, $label . ' must be at most 72 characters.');
        } elseif (!preg_match('/[A-Za-z]/', $value) || !preg_match('/\d/', $value)) {
            $this->fail($field, $label . ' must contain at least one letter and one number.');
        }

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Throw a ValidationException if any rule failed. */
    public function assert(): void
    {
        if (!$this->passes()) {
            throw new ValidationException($this->errors);
        }
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;   // keep the first error per field
    }
}
