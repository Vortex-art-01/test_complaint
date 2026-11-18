<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

use InvalidArgumentException;

/**
 * Входные данные не прошли проверку на сервере.
 *
 * errors(): поле формы => ключ сообщения и параметры.
 */
final class ValidationFailed extends ApplicationException
{
    /**
     * @param array<string, array{key: string, params: array<string, scalar>}> $errors
     */
    private function __construct(
        private readonly array $errors,
    ) {
        $first = reset($errors);
        parent::__construct($first['key'], $first['params']);
    }

    /**
     * @param array<string, scalar> $params
     */
    public static function field(string $field, string $messageKey, array $params = []): self
    {
        return new self([$field => ['key' => $messageKey, 'params' => $params]]);
    }

    /**
     * @param array<string, array{key: string, params: array<string, scalar>}> $errors
     */
    public static function fromErrors(array $errors): self
    {
        if ($errors === []) {
            throw new InvalidArgumentException('Список ошибок пуст.');
        }

        return new self($errors);
    }

    /**
     * @return array<string, array{key: string, params: array<string, scalar>}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
