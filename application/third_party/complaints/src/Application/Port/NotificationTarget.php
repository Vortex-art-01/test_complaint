<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Страница, на которую ведёт ссылка из уведомления. Адрес строит адаптер.
 */
enum NotificationTarget: string
{
    /** Жалоба поставщика; id — test_app_complaint.id. */
    case Complaint = 'complaint';

    /** Решение у уполномоченного органа; id — test_buy_complaint.id. */
    case Decision = 'decision';

    /** Решение у организатора; id — test_buy_complaint.id. */
    case Execution = 'execution';
}
