<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

/**
 * Накопитель ошибок проверки формы: все ошибки сразу, а не первая.
 */
final class ValidationErrors
{
    /** @var array<string, array{key: string, params: array<string, scalar>}> */
    private array $errors = [];

    /**
     * @param array<string, scalar> $params
     */
    public function add(string $field, string $messageKey, array $params = []): void
    {
        $this->errors[$field] ??= ['key' => $messageKey, 'params' => $params];
    }

    public function hasAny(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @throws ValidationFailed
     */
    public function throwIfAny(): void
    {
        if ($this->hasAny()) {
            throw ValidationFailed::fromErrors($this->errors);
        }
    }
}
