<?php
declare(strict_types=1);

namespace Wisdom\Services;

use Wisdom\Core\AppException;
use Wisdom\Core\Request;
use Wisdom\Core\Validator;
use Wisdom\Repositories\SupportRepository;

/** Accepts and validates messages from the public contact form. */
final class SupportService
{
    public function __construct(private SupportRepository $messages)
    {
    }

    /** @param array<string,mixed> $data */
    public function validator(array $data): Validator
    {
        return (new Validator($data))
            ->required('name', 'Name')->length('name', 'Name', 2, 120)
            ->required('email', 'Email')->email('email')
            ->required('subject', 'Subject')->length('subject', 'Subject', 3, 150)
            ->required('message', 'Message')->length('message', 'Message', 10, 4000);
    }

    /** @throws AppException */
    public function submit(string $name, string $email, string $subject, string $message): void
    {
        $validator = $this->validator([
            'name'    => $name,
            'email'   => $email,
            'subject' => $subject,
            'message' => $message,
        ]);

        if (!$validator->passes()) {
            $errors = $validator->errors();
            throw new AppException((string) reset($errors) ?: 'Please check the form and try again.');
        }

        $this->messages->create(trim($name), trim($email), trim($subject), trim($message), Request::ip());
    }
}
