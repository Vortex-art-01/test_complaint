<?php

declare(strict_types=1);

namespace Complaints\Bootstrap;

use Complaints\Application\Port\AccessPolicy;
use Complaints\Application\Port\ApplicationEligibility;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\PdfRenderer;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Port\WorkingCalendar;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Infrastructure\Notification\NotificationTexts;
use Complaints\Infrastructure\Pdf\HtmlToPdfConverter;
use Complaints\Infrastructure\Pdf\MpdfHtmlToPdf;
use Complaints\Infrastructure\Pdf\ProjectHtmlToPdf;
use Complaints\Infrastructure\Pdf\TemplatePdfRenderer;
use Complaints\Infrastructure\Persistence\Database;
use Complaints\Infrastructure\Project\ConfiguredAuthorityResolver;
use Complaints\Infrastructure\Project\ProjectCurrentUserProvider;
use Complaints\Infrastructure\Project\ProjectFileStorage;
use Complaints\Infrastructure\Project\ProjectLotGateway;
use Complaints\Infrastructure\Project\ProjectNotifier;
use Complaints\Infrastructure\Project\ProjectOrganizationDirectory;
use Complaints\Infrastructure\Project\ProjectSignatureVerifier;
use Complaints\Infrastructure\System\ConfiguredWorkingCalendar;
use Complaints\Infrastructure\System\OrganizerAccessPolicy;
use Complaints\Infrastructure\System\StatusApplicationEligibility;
use Complaints\Infrastructure\System\SystemClock;
use PDO;
use RuntimeException;

/**
 * Корень композиции: порты проекта, хранение и настройки модуля.
 * Адаптеры портов можно заменить без правки модуля — ключ adapters конфига.
 */
final class ModuleContainer
{
    /**
     * @param string $languageDirectory application/language (russian/, kazakh/)
     * @param string $templatesDirectory шаблоны PDF модуля (templates/pdf)
     */
    public static function build(ModuleConfig $config, PDO $pdo, string $languageDirectory, string $templatesDirectory): Container
    {
        $container = new Container();
        $timeZone = $config->settings->timeZone;

        $container->set(ComplaintSettings::class, $config->settings);
        $container->set(DisplayDate::class, new DisplayDate($timeZone));
        PersistenceBindings::register($container, $pdo, $config->schema, $timeZone);

        $container->set(Clock::class, new SystemClock($timeZone));
        $container->set(WorkingCalendar::class, new ConfiguredWorkingCalendar($config->holidays, $config->workingDays));
        $container->bind(AccessPolicy::class, OrganizerAccessPolicy::class);
        $container->set(ApplicationEligibility::class, new StatusApplicationEligibility(
            $container->get(Database::class),
            $config->schema->appTable(),
            $config->eligibility['app_status_column'],
            $config->eligibility['app_statuses'],
            $config->schema->appLotTable(),
            $config->eligibility['app_lot_status_column'],
            $config->eligibility['app_lot_statuses'],
        ));
        $container->set(CurrentUserProvider::class, new ProjectCurrentUserProvider($config->roles));
        $container->bind(OrganizationDirectory::class, ProjectOrganizationDirectory::class);
        $container->set(AuthorityResolver::class, new ConfiguredAuthorityResolver($config->authorityOrgId));
        $container->bind(FileStorage::class, ProjectFileStorage::class);
        $container->bind(SignatureVerifier::class, ProjectSignatureVerifier::class);
        $container->set(NotificationTexts::class, new NotificationTexts(
            self::language($languageDirectory . '/russian/complaints_lang.php'),
            self::language($languageDirectory . '/kazakh/complaints_lang.php'),
        ));
        $container->bind(Notifier::class, ProjectNotifier::class);
        $container->bind(ProcurementLotGateway::class, ProjectLotGateway::class);
        $container->set(HtmlToPdfConverter::class, $config->pdfConverter === ModuleConfig::PDF_MPDF
            ? new MpdfHtmlToPdf($config->pdfWorkDirectory)
            : new ProjectHtmlToPdf());
        $container->set(PdfRenderer::class, new TemplatePdfRenderer($container->get(HtmlToPdfConverter::class), $templatesDirectory));

        foreach ($config->adapters as $port => $adapter) {
            $container->bind($port, $adapter);
        }

        return $container;
    }

    /**
     * Строки языкового файла CodeIgniter ($lang[...] = ...).
     *
     * @return array<string, string>
     */
    public static function language(string $file): array
    {
        if (!is_file($file)) {
            throw new RuntimeException('Нет языкового файла модуля ' . $file);
        }

        return (static function (string $file): array {
            $lang = [];
            require $file;

            return $lang;
        })($file);
    }
}
