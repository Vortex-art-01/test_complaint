<?php

declare(strict_types=1);

namespace Complaints\Bootstrap;

use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Infrastructure\Persistence\ProjectSchema;
use Complaints\Infrastructure\Project\RoleMap;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Настройки модуля из application/config/complaints.php ($config['complaints'])
 * с проверкой типов: ошибка конфига видна при первом запросе, а не в середине сценария.
 */
final readonly class ModuleConfig
{
    public const PDF_PROJECT = 'project';
    public const PDF_MPDF = 'mpdf';

    /**
     * @param list<string>                $holidays
     * @param list<string>                $workingDays
     * @param array{app_status_column: string, app_statuses: list<int>, app_lot_status_column: string, app_lot_statuses: list<int>} $eligibility
     * @param array<class-string, class-string> $adapters
     */
    private function __construct(
        public ComplaintSettings $settings,
        public ProjectSchema $schema,
        public RoleMap $roles,
        public int $authorityOrgId,
        public array $holidays,
        public array $workingDays,
        public array $eligibility,
        public string $pdfConverter,
        public string $pdfWorkDirectory,
        public array $adapters,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $config): self
    {
        $timeZone = new DateTimeZone(self::string($config, 'time_zone'));
        $pdfConverter = self::string($config, 'pdf_converter');
        if (!in_array($pdfConverter, [self::PDF_PROJECT, self::PDF_MPDF], true)) {
            throw new InvalidArgumentException("complaints[pdf_converter]: ожидается 'project' или 'mpdf'.");
        }
        $eligibility = self::array($config, 'eligibility');

        return new self(
            new ComplaintSettings(
                $timeZone,
                self::positive($config, 'review_working_days'),
                self::positive($config, 'execution_working_days'),
                self::ints($config, 'complainable_method_ids'),
                self::strings($config, 'organizer_bins'),
                self::positive($config, 'description_max_length'),
                self::positive($config, 'attachment_max_bytes'),
                array_map('strtolower', self::strings($config, 'attachment_extensions')),
                self::positive($config, 'attachment_max_count'),
                self::int($config, 'collection_grace_minutes'),
                self::positive($config, 'page_size'),
            ),
            ProjectSchema::fromConfig(self::array($config, 'project_schema')),
            RoleMap::fromConfig(self::array($config, 'role_map')),
            self::positive($config, 'authority_org_id'),
            self::strings($config, 'holidays'),
            self::strings($config, 'working_days'),
            [
                'app_status_column' => self::string($eligibility, 'app_status_column'),
                'app_statuses' => self::ints($eligibility, 'app_statuses'),
                'app_lot_status_column' => self::string($eligibility, 'app_lot_status_column'),
                'app_lot_statuses' => self::ints($eligibility, 'app_lot_statuses'),
            ],
            $pdfConverter,
            self::string($config, 'pdf_work_directory'),
            self::adapters($config['adapters'] ?? []),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function string(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : throw self::invalid($key, 'непустая строка', $value);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function int(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        return is_int($value) && $value >= 0 ? $value : throw self::invalid($key, 'целое число ≥ 0', $value);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function positive(array $config, string $key): int
    {
        $value = self::int($config, $key);

        return $value > 0 ? $value : throw self::invalid($key, 'целое число > 0', $value);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string|int, mixed>
     */
    private static function array(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        return is_array($value) ? $value : throw self::invalid($key, 'массив', $value);
    }

    /**
     * @param array<string, mixed> $config
     * @return list<int>
     */
    private static function ints(array $config, string $key): array
    {
        $values = array_values(self::array($config, $key));
        foreach ($values as $value) {
            if (!is_int($value)) {
                throw self::invalid($key, 'список целых чисел', $values);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private static function strings(array $config, string $key): array
    {
        $values = array_values(self::array($config, $key));
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw self::invalid($key, 'список строк', $values);
            }
        }

        return $values;
    }

    /**
     * @return array<class-string, class-string>
     */
    private static function adapters(mixed $adapters): array
    {
        if (!is_array($adapters)) {
            throw self::invalid('adapters', 'массив интерфейс => класс', $adapters);
        }
        foreach ($adapters as $port => $adapter) {
            if (!is_string($port) || !interface_exists($port) || !is_string($adapter) || !class_exists($adapter) || !is_subclass_of($adapter, $port)) {
                throw self::invalid('adapters', 'класс, реализующий интерфейс порта', [$port => $adapter]);
            }
        }

        return $adapters;
    }

    private static function invalid(string $key, string $expected, mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('complaints[%s]: ожидается %s, получено %s.', $key, $expected, var_export($value, true)));
    }
}
