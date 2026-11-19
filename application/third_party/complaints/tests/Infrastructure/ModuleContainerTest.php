<?php

declare(strict_types=1);

namespace Complaints\Tests\Infrastructure;

use Complaints\Application\Port\Clock;
use Complaints\Application\Port\NotificationType;
use Complaints\Bootstrap\Container;
use Complaints\Bootstrap\ModuleConfig;
use Complaints\Bootstrap\ModuleContainer;
use Complaints\Tests\Fake\FixedNoonClock;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Корень композиции на настоящем конфиге и языковых файлах модуля.
 */
final class ModuleContainerTest extends TestCase
{
    private static function application(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * @return array<string, mixed>
     */
    private static function projectConfig(): array
    {
        defined('BASEPATH') || define('BASEPATH', self::application() . '/system/');
        defined('APPPATH') || define('APPPATH', self::application() . '/');

        $config = [];
        require self::application() . '/config/complaints.php';
        $complaints = $config['complaints'];
        $complaints['authority_org_id'] = 500;

        return $complaints;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function container(array $config): Container
    {
        return ModuleContainer::build(
            ModuleConfig::fromArray($config),
            new PDO('sqlite::memory:'),
            self::application() . '/language',
            dirname(__DIR__, 2) . '/templates/pdf',
        );
    }

    /**
     * @return list<class-string>
     */
    private static function entryPoints(): array
    {
        $classes = [];
        $source = dirname(__DIR__, 2) . '/src/Application';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = 'Complaints\\Application\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($source) + 1));
            $reflection = new ReflectionClass($class);
            if ($reflection->isInstantiable() && $reflection->hasMethod('execute')) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    public function testEveryScenarioIsAssembledFromProjectConfig(): void
    {
        $container = self::container(self::projectConfig());

        $entryPoints = self::entryPoints();
        foreach ($entryPoints as $class) {
            self::assertInstanceOf($class, $container->get($class));
        }
        self::assertGreaterThan(40, count($entryPoints));
    }

    public function testUnconfiguredAuthorityStopsTheModule(): void
    {
        $config = self::projectConfig();
        $config['authority_org_id'] = 0;

        $this->expectExceptionObject(new InvalidArgumentException('complaints[authority_org_id]: ожидается целое число > 0, получено 0.'));
        ModuleConfig::fromArray($config);
    }

    public function testAdaptersFromConfigReplaceModuleAdapters(): void
    {
        $config = self::projectConfig();
        $config['adapters'] = [Clock::class => FixedNoonClock::class];

        $now = self::container($config)->get(Clock::class)->now();

        self::assertSame('2026-10-16 12:00', $now->format('Y-m-d H:i'));
    }

    public function testEveryMessageKeyHasRussianAndKazakhText(): void
    {
        $russian = ModuleContainer::language(self::application() . '/language/russian/complaints_lang.php');
        $kazakh = ModuleContainer::language(self::application() . '/language/kazakh/complaints_lang.php');

        $keys = array_map(
            static fn (NotificationType $type): string => 'complaints_notification_' . $type->value,
            NotificationType::cases(),
        );
        $source = dirname(__DIR__, 2) . '/src';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $file) {
            if ($file->getExtension() === 'php' && preg_match_all("/'(complaints_error_[a-z_]+)'/", (string) file_get_contents($file->getPathname()), $found) > 0) {
                array_push($keys, ...$found[1]);
            }
        }

        foreach (array_unique($keys) as $key) {
            self::assertArrayHasKey($key, $russian, 'Нет русской строки ' . $key);
            self::assertArrayHasKey($key, $kazakh, 'Нет казахской строки ' . $key);
        }
        self::assertSame([], array_diff_key($russian, $kazakh), 'Строки есть только на русском.');
    }

    public function testEveryViewTextHasRussianAndKazakhText(): void
    {
        $russian = ModuleContainer::language(self::application() . '/language/russian/complaints_lang.php');
        $kazakh = ModuleContainer::language(self::application() . '/language/kazakh/complaints_lang.php');

        // Ключи целиком; префиксы вида 'complaints_signer_role_' . $role дополняются в коде.
        $keys = [];
        $views = self::application() . '/views/complaints';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views)) as $file) {
            if ($file->getExtension() === 'php' && preg_match_all("/complaints_te\\('(complaints_[a-z0-9_]*[a-z0-9])'/", (string) file_get_contents($file->getPathname()), $found) > 0) {
                array_push($keys, ...$found[1]);
            }
        }

        self::assertGreaterThan(100, count(array_unique($keys)));
        foreach (array_unique($keys) as $key) {
            self::assertArrayHasKey($key, $russian, 'Нет русской строки ' . $key);
            self::assertArrayHasKey($key, $kazakh, 'Нет казахской строки ' . $key);
        }
    }
}

