<?php

declare(strict_types=1);

/**
 * Автозагрузчик модуля «Жалобы»: пространство имён Complaints\ -> src/.
 *
 * CodeIgniter 2 не подключает Composer, поэтому модуль регистрирует свой
 * загрузчик. Файл подключают контроллеры модуля и библиотека
 * Complaints_module через require_once — до объявления классов.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Complaints\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
