<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Từ chối nghiệp vụ phát hiện dưới khóa (kiểm lại trong Action). Trả 422 gắn trường, hoặc 403.
 */
final class DomainRejection extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'general', public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 'general', 403);
    }

    public function toValidation(): ValidationException
    {
        return ValidationException::withMessages([$this->field => $this->getMessage()])->status($this->status);
    }
}
