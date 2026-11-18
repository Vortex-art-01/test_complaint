<?php

declare(strict_types=1);

namespace Complaints\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * Ошибка сценария, которую видит пользователь.
 *
 * Текст не хранится: контроллер берёт сообщение из языкового файла
 * по ключу messageKey() и подставляет params().
 */
abstract class ApplicationException extends RuntimeException
{
    /**
     * @param array<string, scalar> $params
     */
    public function __construct(
        private readonly string $messageKey,
        private readonly array $params = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($messageKey, 0, $previous);
    }

    public function messageKey(): string
    {
        return $this->messageKey;
    }

    /**
     * @return array<string, scalar>
     */
    public function params(): array
    {
        return $this->params;
    }
}
