<?php

declare(strict_types=1);

namespace Complaints\Tests\Infrastructure;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\Access\Permission;
use Complaints\Application\Port\Notification;
use Complaints\Application\Port\NotificationTarget;
use Complaints\Application\Port\NotificationType;
use Complaints\Infrastructure\Notification\NotificationTexts;
use Complaints\Infrastructure\Project\ProjectFileStorage;
use Complaints\Infrastructure\Project\ProjectServiceMissing;
use Complaints\Infrastructure\Project\RoleMap;
use Complaints\Infrastructure\System\ConfiguredWorkingCalendar;
use Complaints\Infrastructure\System\OrganizerAccessPolicy;
use Complaints\Tests\Support\World;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AdaptersTest extends TestCase
{
    public function testCalendarSkipsHolidaysAndCountsTransferredWorkingDays(): void
    {
        $calendar = new ConfiguredWorkingCalendar(['2026-10-26'], ['2026-10-24']);
        $thursday = new DateTimeImmutable('2026-10-22 00:00:00+05:00');

        self::assertSame('2026-10-24', $calendar->addWorkingDays($thursday, 2)->format('Y-m-d'), 'Рабочая суббота считается.');
        self::assertSame('2026-10-27', $calendar->addWorkingDays($thursday, 3)->format('Y-m-d'), 'Праздник пропускается.');
        self::assertSame($thursday, $calendar->addWorkingDays($thursday, 0));
        self::assertSame('2026-10-25', $calendar->addCalendarDays($thursday, 3)->format('Y-m-d'));

        $this->expectException(InvalidArgumentException::class);
        new ConfiguredWorkingCalendar(['26.10.2026'], []);
    }

    public function testRoleMapTranslatesProjectRoles(): void
    {
        $map = RoleMap::fromConfig(['head' => ['supplier', 'supplier_head'], 'staff' => ['supplier']]);

        self::assertSame([ModuleRole::Supplier, ModuleRole::SupplierHead], $map->moduleRoles(['staff', 'head', 'unknown']));

        $this->expectException(InvalidArgumentException::class);
        RoleMap::fromConfig(['head' => ['director']]);
    }

    public function testNotificationTextsFillPlaceholdersAndLinkToModulePage(): void
    {
        $texts = new NotificationTexts(
            ['complaints_notification_complaint_signed' => 'Жалоба {number} подписана.'],
            ['complaints_notification_complaint_signed' => '{number} шағымына қол қойылды.'],
        );

        $message = $texts->message(new Notification(
            NotificationType::ComplaintSigned,
            ['number' => 'Ж-000015'],
            NotificationTarget::Complaint,
            15,
        ));

        self::assertSame('Жалоба Ж-000015 подписана.', $message->in('ru'));
        self::assertSame('Ж-000015 шағымына қол қойылды.', $message->in('kz'));
        self::assertSame('complaint_supplier/show/15', $message->path);
    }

    public function testOnlyBuyOrganizerExecutesDecisions(): void
    {
        $world = new World();
        $policy = new OrganizerAccessPolicy($world->procurement);
        $organizer = new CurrentUser(World::ORGANIZER, World::ORGANIZER_ORG, [ModuleRole::Organizer]);
        $stranger = new CurrentUser(77, 777, [ModuleRole::Organizer]);

        self::assertTrue($policy->isAllowed($organizer, Permission::ExecuteDecision, World::BUY));
        self::assertFalse($policy->isAllowed($stranger, Permission::ExecuteDecision, World::BUY));
        self::assertTrue($policy->isAllowed($stranger, Permission::ReviewComplaints, World::BUY));
    }

    public function testUnconnectedProjectAdapterNamesItsMethod(): void
    {
        $this->expectExceptionObject(ProjectServiceMissing::in(ProjectFileStorage::class . '::describe'));
        (new ProjectFileStorage())->describe(1);
    }
}
