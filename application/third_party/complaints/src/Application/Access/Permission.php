<?php

declare(strict_types=1);

namespace Complaints\Application\Access;

/**
 * Действие модуля над закупкой, разрешение на которое даёт проект (порт AccessPolicy).
 */
enum Permission: string
{
    case FileComplaint = 'file_complaint';
    case ReviewComplaints = 'review_complaints';
    case CoordinateDecision = 'coordinate_decision';
    case ApproveDecision = 'approve_decision';
    case ExecuteDecision = 'execute_decision';

    /**
     * Роль модуля, без которой действие недоступно.
     */
    public function requiredRole(): ModuleRole
    {
        return match ($this) {
            self::FileComplaint => ModuleRole::Supplier,
            self::ReviewComplaints => ModuleRole::Reviewer,
            self::CoordinateDecision => ModuleRole::Coordinator,
            self::ApproveDecision => ModuleRole::Approver,
            self::ExecuteDecision => ModuleRole::Organizer,
        };
    }
}
