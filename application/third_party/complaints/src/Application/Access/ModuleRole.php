<?php

declare(strict_types=1);

namespace Complaints\Application\Access;

/**
 * Роли модуля. Адаптер CurrentUserProvider сопоставляет им роли проекта.
 */
enum ModuleRole: string
{
    /** Сотрудник организации-поставщика: создаёт жалобы. */
    case Supplier = 'supplier';

    /** «Руководитель» организации-поставщика: подписывает жалобы. */
    case SupplierHead = 'supplier_head';

    /** «Рассматривающий жалобы» уполномоченного органа. */
    case Reviewer = 'reviewer';

    /** «Согласующий жалобы» уполномоченного органа. */
    case Coordinator = 'coordinator';

    /** «Утверждающий жалобы» уполномоченного органа. */
    case Approver = 'approver';

    /** Сотрудник организатора закупки: исполняет решения. */
    case Organizer = 'organizer';
}
