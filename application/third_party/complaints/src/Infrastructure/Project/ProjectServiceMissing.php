<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Project;

use LogicException;

/**
 * Адаптер порта ещё не подключён к сервису проекта: место отмечено
 * комментарием «ПРОЕКТ: заменить вызовом существующего сервиса».
 */
final class ProjectServiceMissing extends LogicException
{
    public static function in(string $method): self
    {
        return new self(sprintf('ПРОЕКТ: адаптер %s не подключён к сервису проекта.', $method));
    }
}
