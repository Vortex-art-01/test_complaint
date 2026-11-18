<?php

declare(strict_types=1);

namespace Complaints\Tests\Web;

use RuntimeException;
use Throwable;

/**
 * Минимальный «супер-объект» CodeIgniter для отрисовки представлений модуля
 * без фреймворка: язык, адрес, CSRF, конфиг и загрузчик представлений.
 * Загрузчик, как в CodeIgniter 2, накапливает переменные между вызовами view().
 */
final class CodeIgniterStub
{
    public static ?self $instance = null;

    public object $lang;
    public object $uri;
    public object $security;
    public object $config;
    public object $load;

    /** @var array<string, mixed> */
    private array $cachedVars = [];

    /**
     * @param array<string, string> $lines  строки языкового файла
     * @param array<string, mixed>  $config $config['complaints']
     */
    public function __construct(
        private readonly string $viewsDirectory,
        array $lines,
        string $language,
        array $config,
    ) {
        $this->lang = new class ($lines) {
            /** @param array<string, string> $lines */
            public function __construct(private readonly array $lines)
            {
            }

            public function line(string $key): string|false
            {
                return $this->lines[$key] ?? false;
            }
        };
        $this->uri = new class ($language) {
            public function __construct(private readonly string $language)
            {
            }

            public function segment(int $index): string|false
            {
                return $index === 1 ? $this->language : false;
            }
        };
        $this->security = new class () {
            public function get_csrf_token_name(): string
            {
                return 'ci_csrf_token';
            }

            public function get_csrf_hash(): string
            {
                return 'csrf-test-hash';
            }
        };
        $this->config = new class ($config) {
            /** @param array<string, mixed> $config */
            public function __construct(private readonly array $config)
            {
            }

            public function item(string $key): mixed
            {
                return $key === 'complaints' ? $this->config : false;
            }
        };
        $this->load = $this;
        self::$instance = $this;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function view(string $view, array $vars = [], bool $return = false): ?string
    {
        $file = $this->viewsDirectory . '/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Нет представления ' . $view);
        }
        $this->cachedVars = array_merge($this->cachedVars, $vars);

        // Представление выполняется в контексте объекта: в нём доступен $this->load->view().
        $render = function (string $__file, array $__vars): string {
            extract($__vars);
            ob_start();
            try {
                include $__file;
            } catch (Throwable $error) {
                ob_end_clean();
                throw $error;
            }

            return (string) ob_get_clean();
        };
        $output = $render($file, $this->cachedVars);

        if ($return) {
            return $output;
        }
        echo $output;

        return null;
    }
}
