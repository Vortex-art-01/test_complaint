<?php

declare(strict_types=1);

namespace Complaints\Bootstrap;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Контейнер зависимостей модуля: интерфейсы регистрируются явно
 * (реализации портов и репозиториев, настройки), остальные классы
 * собираются по типам параметров конструктора. Один экземпляр на запрос.
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    public function set(string $id, object $service): void
    {
        $this->instances[$id] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            /** @var T */
            return $this->instances[$id];
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new LogicException(sprintf('Не зарегистрирована реализация %s.', $id));
        }

        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                throw new LogicException(sprintf('Параметр $%s в %s нельзя собрать автоматически.', $parameter->getName(), $id));
            }
            $arguments[] = $this->get($type->getName());
        }

        $instance = $reflection->newInstanceArgs($arguments);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
