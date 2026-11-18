<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

/**
 * Статус жалобы поставщика (test_app_complaint.status).
 */
enum AppComplaintStatus: string
{
    case Draft = 'draft';
    case AwaitingSignature = 'awaiting_signature';
    case Signed = 'signed';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
    case InReview = 'in_review';
    case Reviewed = 'reviewed';

    /**
     * Удалить жалобу можно только до подачи.
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::AwaitingSignature, self::Signed], true);
    }

    /**
     * Содержимое, лот и руководителя меняют только в черновике.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Действующая подпись руководителя есть только в этих статусах.
     */
    public function hasHeadSignature(): bool
    {
        return in_array($this, [self::Signed, self::Submitted, self::Withdrawn, self::InReview, self::Reviewed], true);
    }
}
