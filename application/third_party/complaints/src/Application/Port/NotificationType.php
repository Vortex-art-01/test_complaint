<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

/**
 * Вид уведомления. Тексты на ru и kz — в языковых файлах модуля
 * (ключ complaints_notification_<значение>).
 */
enum NotificationType: string
{
    case ComplaintAwaitingSignature = 'complaint_awaiting_signature';
    case ComplaintSigned = 'complaint_signed';
    case ComplaintsReceived = 'complaints_received';
    case DecisionForCoordination = 'decision_for_coordination';
    case DecisionForApproval = 'decision_for_approval';
    case DecisionRecalled = 'decision_recalled';
    case DecisionApproved = 'decision_approved';
    case ComplaintAnswered = 'complaint_answered';
    case DecisionRequiresExecution = 'decision_requires_execution';
    case DecisionWithoutExecution = 'decision_without_execution';
    case DecisionExecuted = 'decision_executed';
    case ReviewDeadline = 'review_deadline';
    case ExecutionDeadline = 'execution_deadline';
}
