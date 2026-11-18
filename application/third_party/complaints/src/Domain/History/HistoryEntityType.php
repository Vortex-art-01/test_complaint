<?php

declare(strict_types=1);

namespace Complaints\Domain\History;

/**
 * Сущность, чей переход статуса записан в test_complaint_status_history.
 */
enum HistoryEntityType: string
{
    case AppComplaint = 'app_complaint';
    case BuyComplaint = 'buy_complaint';
    case BuyComplaintLot = 'buy_complaint_lot';
}
