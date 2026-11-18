<?php

declare(strict_types=1);

namespace Complaints\Tests\Support;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\CreateComplaintDraftCommand;
use Complaints\Application\AppComplaint\UseCase\SendComplaintForSignature;
use Complaints\Application\AppComplaint\UseCase\SignComplaint;
use Complaints\Application\AppComplaint\UseCase\SubmitComplaint;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraft;
use Complaints\Application\AppComplaint\UseCase\UpdateComplaintDraftCommand;
use Complaints\Application\Collection\CollectSubmittedComplaints;
use Complaints\Application\Port\AccessPolicy;
use Complaints\Application\Port\ApplicationEligibility;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\Notifier;
use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\PdfRenderer;
use Complaints\Application\Port\ProcurementLotGateway;
use Complaints\Application\Port\SignatureVerifier;
use Complaints\Application\Port\UserCard;
use Complaints\Application\Port\VerifiedSignature;
use Complaints\Application\Port\WorkingCalendar;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\ApplicationCard;
use Complaints\Application\Repository\ApplicationLotCard;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\BuyLotCard;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Application\Repository\StatusHistoryRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Shared\ComplaintSettings;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Application\Shared\LocalizedText;
use Complaints\Bootstrap\Container;
use Complaints\Tests\Fake\FakeAccessPolicy;
use Complaints\Tests\Fake\FakeAuthorities;
use Complaints\Tests\Fake\FakeCalendar;
use Complaints\Tests\Fake\FakeCurrentUser;
use Complaints\Tests\Fake\FakeDirectory;
use Complaints\Tests\Fake\FakeEligibility;
use Complaints\Tests\Fake\FakeFiles;
use Complaints\Tests\Fake\FakeLotGateway;
use Complaints\Tests\Fake\FakeNotifier;
use Complaints\Tests\Fake\FakePdf;
use Complaints\Tests\Fake\FakeVerifier;
use Complaints\Tests\Fake\FixedClock;
use Complaints\Tests\Fake\ImmediateTransactions;
use Complaints\Tests\Fake\InMemoryAnswers;
use Complaints\Tests\Fake\InMemoryAppComplaints;
use Complaints\Tests\Fake\InMemoryAttachments;
use Complaints\Tests\Fake\InMemoryBuyComplaints;
use Complaints\Tests\Fake\InMemoryDocuments;
use Complaints\Tests\Fake\InMemoryHistory;
use Complaints\Tests\Fake\InMemoryProcurement;
use Complaints\Tests\Fake\InMemoryQueries;
use Complaints\Tests\Fake\InMemorySignatures;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Тестовый стенд: ядро модуля на фейках и данные из макетов
 * (закупка 125407-1, протокол итогов 14.10.2026 18:00, срок обжалования 15–19.10).
 */
final class World
{
    public const AUTHOR = 10;
    public const HEAD = 11;
    public const OTHER_HEAD = 12;
    public const REVIEWER = 20;
    public const COORDINATOR = 21;
    public const APPROVER = 22;
    public const SECOND_REVIEWER = 23;
    public const ORGANIZER = 30;
    public const SUPPLIER2_AUTHOR = 40;
    public const SUPPLIER2_HEAD = 41;

    public const SUPPLIER = 100;
    public const SUPPLIER2 = 200;
    public const AUTHORITY = 500;
    public const ORGANIZER_ORG = 900;

    public const BUY = 1;
    public const LOT_A = 1;
    public const LOT_B = 2;
    public const APP_LOT_A = 1;
    public const APP_LOT_B = 2;
    public const APP2_LOT_A = 3;

    private const USERS = [
        self::AUTHOR => [self::SUPPLIER, 'Иванов Алексей Андреевич', [ModuleRole::Supplier]],
        self::HEAD => [self::SUPPLIER, 'Сейтов Бауыржан Нурланович', [ModuleRole::Supplier, ModuleRole::SupplierHead]],
        self::OTHER_HEAD => [self::SUPPLIER, 'Ахметова Гульнар Кайратовна', [ModuleRole::Supplier, ModuleRole::SupplierHead]],
        self::SUPPLIER2_AUTHOR => [self::SUPPLIER2, 'Петров Пётр', [ModuleRole::Supplier]],
        self::SUPPLIER2_HEAD => [self::SUPPLIER2, 'Каримов Ержан', [ModuleRole::Supplier, ModuleRole::SupplierHead]],
        self::REVIEWER => [self::AUTHORITY, 'Сидорова Айгуль Болатовна', [ModuleRole::Reviewer]],
        self::SECOND_REVIEWER => [self::AUTHORITY, 'Омаров Данияр', [ModuleRole::Reviewer]],
        self::COORDINATOR => [self::AUTHORITY, 'Ахметов Берик Канатович', [ModuleRole::Coordinator]],
        self::APPROVER => [self::AUTHORITY, 'Касымова Динара Ерлановна', [ModuleRole::Approver]],
        self::ORGANIZER => [self::ORGANIZER_ORG, 'Нурланова Айжан', [ModuleRole::Organizer]],
    ];

    private const ORGANIZATIONS = [
        self::SUPPLIER => ['180340021345', 'ТОО «Поставщик-1»'],
        self::SUPPLIER2 => ['000000000002', 'ТОО «Поставщик-2»'],
        self::AUTHORITY => ['000000000001', 'Комитет внутреннего государственного аудита'],
        self::ORGANIZER_ORG => ['090340007747', 'ТОО «СК-Фармация»'],
    ];

    public readonly Container $container;
    public readonly FakeCurrentUser $users;
    public readonly FakeDirectory $directory;
    public readonly FakeAuthorities $authorities;
    public readonly FakeFiles $files;
    public readonly FakePdf $pdf;
    public readonly FakeVerifier $verifier;
    public readonly FakeNotifier $notifier;
    public readonly FakeCalendar $calendar;
    public readonly FixedClock $clock;
    public readonly FakeLotGateway $gateway;
    public readonly FakeEligibility $eligibility;
    public readonly FakeAccessPolicy $policy;
    public readonly InMemoryProcurement $procurement;
    public readonly InMemoryAppComplaints $complaints;
    public readonly InMemoryBuyComplaints $decisions;
    public readonly InMemoryAnswers $answers;
    public readonly InMemoryDocuments $documents;
    public readonly InMemorySignatures $signatures;
    public readonly InMemoryAttachments $attachments;
    public readonly InMemoryHistory $history;

    public function __construct()
    {
        $timeZone = new DateTimeZone('Asia/Almaty');
        $this->users = new FakeCurrentUser();
        $this->directory = new FakeDirectory();
        $this->authorities = new FakeAuthorities($this->directory);
        $this->files = new FakeFiles();
        $this->pdf = new FakePdf();
        $this->verifier = new FakeVerifier();
        $this->notifier = new FakeNotifier();
        $this->calendar = new FakeCalendar();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-10-16 11:00:00+05:00'));
        $this->gateway = new FakeLotGateway();
        $this->eligibility = new FakeEligibility();
        $this->policy = new FakeAccessPolicy();
        $this->procurement = new InMemoryProcurement();
        $this->complaints = new InMemoryAppComplaints();
        $this->decisions = new InMemoryBuyComplaints();
        $this->answers = new InMemoryAnswers($this->decisions);
        $this->documents = new InMemoryDocuments();
        $this->signatures = new InMemorySignatures();
        $this->attachments = new InMemoryAttachments();
        $this->history = new InMemoryHistory();

        $this->container = new Container();
        foreach ([
            CurrentUserProvider::class => $this->users,
            AccessPolicy::class => $this->policy,
            OrganizationDirectory::class => $this->directory,
            AuthorityResolver::class => $this->authorities,
            FileStorage::class => $this->files,
            PdfRenderer::class => $this->pdf,
            SignatureVerifier::class => $this->verifier,
            Notifier::class => $this->notifier,
            WorkingCalendar::class => $this->calendar,
            Clock::class => $this->clock,
            ProcurementLotGateway::class => $this->gateway,
            ApplicationEligibility::class => $this->eligibility,
            TransactionManager::class => new ImmediateTransactions(),
            ProcurementRepository::class => $this->procurement,
            AppComplaintRepository::class => $this->complaints,
            BuyComplaintRepository::class => $this->decisions,
            AnswerRepository::class => $this->answers,
            DocumentRepository::class => $this->documents,
            SignatureRepository::class => $this->signatures,
            AttachmentRepository::class => $this->attachments,
            StatusHistoryRepository::class => $this->history,
            ComplaintQueries::class => new InMemoryQueries($this->complaints, $this->decisions, $this->procurement, $this->documents, $this->signatures),
            ComplaintSettings::class => new ComplaintSettings($timeZone, 3, 3, 2, [2], ['090340007747'], 500, 20 * 1024 * 1024, ['pdf', 'docx', 'xlsx', 'jpg'], 10, 5, 20),
            DisplayDate::class => new DisplayDate($timeZone),
        ] as $id => $service) {
            $this->container->set($id, $service);
        }

        $this->seed();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function get(string $class): object
    {
        return $this->container->get($class);
    }

    /**
     * Следующие вызовы — от имени пользователя; его сертификат подписывает документы.
     */
    public function actAs(int $userId): void
    {
        [$organizationId, , $roles] = self::USERS[$userId];
        $this->users->user = new CurrentUser($userId, $organizationId, $roles);
        $this->verifier->signingUserId = $userId;
    }

    public function at(string $moment): void
    {
        $this->clock->now = new DateTimeImmutable($moment);
    }

    /**
     * Черновик с заполненной сутью жалобы.
     */
    public function draft(int $author = self::AUTHOR, int $appLotId = self::APP_LOT_A, int $head = self::HEAD): int
    {
        $this->actAs($author);
        $id = $this->get(CreateComplaintDraft::class)->execute(new CreateComplaintDraftCommand(self::BUY, $appLotId, $head));
        $this->get(UpdateComplaintDraft::class)->execute(new UpdateComplaintDraftCommand(
            $id,
            '+7 701 123 45 67',
            'Комиссия необоснованно отклонила заявку: регистрационное удостоверение действует до 2028 г.',
        ));

        return $id;
    }

    /**
     * Жалоба, подписанная руководителем.
     */
    public function signed(int $author = self::AUTHOR, int $appLotId = self::APP_LOT_A, int $head = self::HEAD): int
    {
        $id = $this->draft($author, $appLotId, $head);
        $this->get(SendComplaintForSignature::class)->execute($id);
        $this->actAs($head);
        $this->get(SignComplaint::class)->execute($id, base64_encode('cms-' . $id));
        $this->actAs($author);

        return $id;
    }

    /**
     * Поданная жалоба.
     */
    public function submitted(int $author = self::AUTHOR, int $appLotId = self::APP_LOT_A, int $head = self::HEAD): int
    {
        $id = $this->signed($author, $appLotId, $head);
        $this->get(SubmitComplaint::class)->execute($id);

        return $id;
    }

    /**
     * Сбор жалоб после окончания срока обжалования (19.10 23:59).
     */
    public function collect(): void
    {
        $this->at('2026-10-20 00:10:00+05:00');
        $this->get(CollectSubmittedComplaints::class)->execute();
    }

    private function seed(): void
    {
        foreach (self::ORGANIZATIONS as $id => [$bin, $name]) {
            $this->directory->organizations[$id] = new OrganizationCard($id, $bin, new LocalizedText($name, $name), new LocalizedText('г. Астана', 'Астана қ.'));
        }
        foreach (self::USERS as $id => [$organizationId, $name]) {
            $iin = sprintf('85010130%04d', $id);
            $this->directory->users[$id] = new UserCard($id, $organizationId, $name, $iin, 'user' . $id . '@example.kz', 'Сотрудник');
            $this->verifier->certificates[$id] = new VerifiedSignature($name, $iin, self::ORGANIZATIONS[$organizationId][0], 'CN=' . $name);
        }
        $this->directory->heads = [self::SUPPLIER => [self::HEAD, self::OTHER_HEAD], self::SUPPLIER2 => [self::SUPPLIER2_HEAD]];

        $this->authorities->authorityOrgId = self::AUTHORITY;
        $this->authorities->employees = [
            ModuleRole::Reviewer->value => [self::REVIEWER, self::SECOND_REVIEWER],
            ModuleRole::Coordinator->value => [self::COORDINATOR],
            ModuleRole::Approver->value => [self::APPROVER],
        ];
        $this->policy->organizers = [self::BUY => self::ORGANIZER_ORG];

        $this->procurement->buys[self::BUY] = new BuyCard(
            self::BUY,
            '125407-1',
            new LocalizedText('Закуп лекарственных средств на 2027 г.', '2027 жылға арналған дәрілік заттарды сатып алу'),
            2,
            self::ORGANIZER_ORG,
            new DateTimeImmutable('2026-09-01 10:00:00+05:00'),
        );
        $this->procurement->buyLots[self::LOT_A] = new BuyLotCard(self::LOT_A, self::BUY, '4889720-Т1', new LocalizedText('Амоксициллин, таблетки 500 мг', 'Амоксициллин'));
        $this->procurement->buyLots[self::LOT_B] = new BuyLotCard(self::LOT_B, self::BUY, '4889721-Т1', new LocalizedText('Цефтриаксон, порошок 1 г', 'Цефтриаксон'));
        $this->procurement->applications[1] = new ApplicationCard(1, self::BUY, self::SUPPLIER);
        $this->procurement->applications[2] = new ApplicationCard(2, self::BUY, self::SUPPLIER2);
        $this->procurement->applicationLots[self::APP_LOT_A] = new ApplicationLotCard(self::APP_LOT_A, 1, self::LOT_A);
        $this->procurement->applicationLots[self::APP_LOT_B] = new ApplicationLotCard(self::APP_LOT_B, 1, self::LOT_B);
        $this->procurement->applicationLots[self::APP2_LOT_A] = new ApplicationLotCard(self::APP2_LOT_A, 2, self::LOT_A);

        $published = new DateTimeImmutable('2026-10-14 18:00:00+05:00');
        $this->gateway->protocols = [self::LOT_A => $published, self::LOT_B => $published];
    }
}
