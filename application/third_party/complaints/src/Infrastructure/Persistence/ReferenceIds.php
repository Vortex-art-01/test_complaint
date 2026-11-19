<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use UnexpectedValueException;

/**
 * id строк справочников ref_* (миграция 001) и их соответствие статусам и типам модуля.
 *
 * Константы подставляются в SQL числами: это значения модуля, а не ввод пользователя.
 * Списки *_APPROVED, *_REQUIRES_EXECUTION, *_DELETABLE — для IN (…).
 * Статус исполнения решения входит в статус решения: «утверждено» — это 5, 6 или 7.
 */
final class ReferenceIds
{
    // ref_trd_complaint_st
    public const DECISION_IN_REVIEW = 1;
    public const DECISION_ON_COORDINATION = 2;
    public const DECISION_ON_APPROVAL = 3;
    public const DECISION_RECALLED = 4;
    public const DECISION_APPROVED_NOT_REQUIRED = 5;
    public const DECISION_APPROVED_IN_PROGRESS = 6;
    public const DECISION_APPROVED_EXECUTED = 7;
    public const DECISION_APPROVED = self::DECISION_APPROVED_NOT_REQUIRED . ', ' . self::DECISION_APPROVED_IN_PROGRESS
        . ', ' . self::DECISION_APPROVED_EXECUTED;

    // ref_complaint_review_method
    public const METHOD_PER_COMPLAINT = 1;
    public const METHOD_PER_LOT = 2;

    // ref_trd_complaint_role
    public const ROLE_REVIEWER = 1;
    public const ROLE_COORDINATOR = 2;
    public const ROLE_APPROVER = 3;

    // ref_trd_complaint_lots_decision
    public const LOT_CANCEL_PURCHASE = 1;
    public const LOT_REVISE_RESULTS = 2;
    public const LOT_NO_ACTION = 3;
    public const LOT_REQUIRES_EXECUTION = self::LOT_CANCEL_PURCHASE . ', ' . self::LOT_REVISE_RESULTS;

    // ref_trd_complaint_date_type
    public const DATE_APPEAL = 1;
    public const DATE_REVIEW = 2;
    public const DATE_EXECUTION = 3;

    // ref_trd_complaint_docs_type
    public const TRD_DOC_CONSOLIDATED = 1;
    public const TRD_DOC_LOT_ANSWER = 2;
    public const TRD_DOC_LOT_ANSWER_FILE = 3;

    // ref_app_complaint_st
    public const COMPLAINT_DRAFT = 1;
    public const COMPLAINT_AWAITING_SIGNATURE = 2;
    public const COMPLAINT_SIGNED = 3;
    public const COMPLAINT_SUBMITTED = 4;
    public const COMPLAINT_WITHDRAWN = 5;
    public const COMPLAINT_IN_REVIEW = 6;
    public const COMPLAINT_REVIEWED = 7;
    public const COMPLAINT_DELETABLE = self::COMPLAINT_DRAFT . ', ' . self::COMPLAINT_AWAITING_SIGNATURE
        . ', ' . self::COMPLAINT_SIGNED;

    // ref_app_complaint_docs_type
    public const APP_DOC_COMPLAINT = 1;
    public const APP_DOC_COMPLAINT_FILE = 2;
    public const APP_DOC_ANSWER = 3;
    public const APP_DOC_ANSWER_FILE = 4;

    // ref_app_complaint_lots_verdict
    public const VERDICT_SATISFIED = 1;
    public const VERDICT_REJECTED = 2;

    public static function complaintStatus(AppComplaintStatus $status): int
    {
        return match ($status) {
            AppComplaintStatus::Draft => self::COMPLAINT_DRAFT,
            AppComplaintStatus::AwaitingSignature => self::COMPLAINT_AWAITING_SIGNATURE,
            AppComplaintStatus::Signed => self::COMPLAINT_SIGNED,
            AppComplaintStatus::Submitted => self::COMPLAINT_SUBMITTED,
            AppComplaintStatus::Withdrawn => self::COMPLAINT_WITHDRAWN,
            AppComplaintStatus::InReview => self::COMPLAINT_IN_REVIEW,
            AppComplaintStatus::Reviewed => self::COMPLAINT_REVIEWED,
        };
    }

    public static function complaintStatusOf(int $id): AppComplaintStatus
    {
        return match ($id) {
            self::COMPLAINT_DRAFT => AppComplaintStatus::Draft,
            self::COMPLAINT_AWAITING_SIGNATURE => AppComplaintStatus::AwaitingSignature,
            self::COMPLAINT_SIGNED => AppComplaintStatus::Signed,
            self::COMPLAINT_SUBMITTED => AppComplaintStatus::Submitted,
            self::COMPLAINT_WITHDRAWN => AppComplaintStatus::Withdrawn,
            self::COMPLAINT_IN_REVIEW => AppComplaintStatus::InReview,
            self::COMPLAINT_REVIEWED => AppComplaintStatus::Reviewed,
            default => throw self::unknown('ref_app_complaint_st', $id),
        };
    }

    /**
     * Статусы решения в справочнике; утверждённому соответствуют три строки.
     *
     * @return list<int>
     */
    public static function decisionStatuses(BuyComplaintStatus $status): array
    {
        return match ($status) {
            BuyComplaintStatus::InReview => [self::DECISION_IN_REVIEW],
            BuyComplaintStatus::OnCoordination => [self::DECISION_ON_COORDINATION],
            BuyComplaintStatus::OnApproval => [self::DECISION_ON_APPROVAL],
            BuyComplaintStatus::Recalled => [self::DECISION_RECALLED],
            BuyComplaintStatus::Approved => [
                self::DECISION_APPROVED_NOT_REQUIRED,
                self::DECISION_APPROVED_IN_PROGRESS,
                self::DECISION_APPROVED_EXECUTED,
            ],
        };
    }

    /**
     * Статус решения, кроме утверждённого: у него id зависит от исполнения.
     */
    public static function decisionStatus(BuyComplaintStatus $status): int
    {
        $ids = self::decisionStatuses($status);
        if (count($ids) !== 1) {
            throw new UnexpectedValueException('Статус утверждённого решения зависит от исполнения: ' . $status->value);
        }

        return $ids[0];
    }

    public static function approvedStatus(ExecutionStatus $execution): int
    {
        return match ($execution) {
            ExecutionStatus::NotRequired => self::DECISION_APPROVED_NOT_REQUIRED,
            ExecutionStatus::InProgress => self::DECISION_APPROVED_IN_PROGRESS,
            ExecutionStatus::Executed => self::DECISION_APPROVED_EXECUTED,
        };
    }

    public static function decisionStatusOf(int $id): BuyComplaintStatus
    {
        return match ($id) {
            self::DECISION_IN_REVIEW => BuyComplaintStatus::InReview,
            self::DECISION_ON_COORDINATION => BuyComplaintStatus::OnCoordination,
            self::DECISION_ON_APPROVAL => BuyComplaintStatus::OnApproval,
            self::DECISION_RECALLED => BuyComplaintStatus::Recalled,
            self::DECISION_APPROVED_NOT_REQUIRED,
            self::DECISION_APPROVED_IN_PROGRESS,
            self::DECISION_APPROVED_EXECUTED => BuyComplaintStatus::Approved,
            default => throw self::unknown('ref_trd_complaint_st', $id),
        };
    }

    /**
     * Статус исполнения решения; null — решение не утверждено.
     */
    public static function executionStatusOf(int $decisionStatusId): ?ExecutionStatus
    {
        return match ($decisionStatusId) {
            self::DECISION_APPROVED_NOT_REQUIRED => ExecutionStatus::NotRequired,
            self::DECISION_APPROVED_IN_PROGRESS => ExecutionStatus::InProgress,
            self::DECISION_APPROVED_EXECUTED => ExecutionStatus::Executed,
            default => null,
        };
    }

    public static function isApproved(int $decisionStatusId): bool
    {
        return self::executionStatusOf($decisionStatusId) !== null;
    }

    public static function reviewMethod(ReviewMethod $method): int
    {
        return match ($method) {
            ReviewMethod::PerComplaint => self::METHOD_PER_COMPLAINT,
            ReviewMethod::PerLot => self::METHOD_PER_LOT,
        };
    }

    public static function reviewMethodOf(int $id): ReviewMethod
    {
        return match ($id) {
            self::METHOD_PER_COMPLAINT => ReviewMethod::PerComplaint,
            self::METHOD_PER_LOT => ReviewMethod::PerLot,
            default => throw self::unknown('ref_complaint_review_method', $id),
        };
    }

    public static function decisionType(DecisionType $type): int
    {
        return match ($type) {
            DecisionType::CancelPurchase => self::LOT_CANCEL_PURCHASE,
            DecisionType::ReviseResults => self::LOT_REVISE_RESULTS,
            DecisionType::NoAction => self::LOT_NO_ACTION,
        };
    }

    public static function decisionTypeOf(int $id): DecisionType
    {
        return match ($id) {
            self::LOT_CANCEL_PURCHASE => DecisionType::CancelPurchase,
            self::LOT_REVISE_RESULTS => DecisionType::ReviseResults,
            self::LOT_NO_ACTION => DecisionType::NoAction,
            default => throw self::unknown('ref_trd_complaint_lots_decision', $id),
        };
    }

    public static function verdict(Verdict $verdict): int
    {
        return match ($verdict) {
            Verdict::Satisfied => self::VERDICT_SATISFIED,
            Verdict::Rejected => self::VERDICT_REJECTED,
        };
    }

    public static function verdictOf(int $id): Verdict
    {
        return match ($id) {
            self::VERDICT_SATISFIED => Verdict::Satisfied,
            self::VERDICT_REJECTED => Verdict::Rejected,
            default => throw self::unknown('ref_app_complaint_lots_verdict', $id),
        };
    }

    private static function unknown(string $table, int $id): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Неизвестный id %d в %s: модуль знает только строки миграции 001.', $id, $table));
    }
}
