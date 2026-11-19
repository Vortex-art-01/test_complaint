<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

/**
 * Статус решения по жалобам на закупку (trd_complaint.status_id → ref_trd_complaint_st;
 * утверждённому соответствуют три строки — по статусу исполнения).
 */
enum BuyComplaintStatus: string
{
    case InReview = 'in_review';
    case OnCoordination = 'on_coordination';
    case OnApproval = 'on_approval';
    case Recalled = 'recalled';
    case Approved = 'approved';

    /**
     * Ответы, вердикты и типы решений меняют только в этом статусе.
     */
    public function isEditable(): bool
    {
        return $this === self::InReview;
    }

    /**
     * Документ на маршруте согласования: его можно отозвать.
     */
    public function isOnRoute(): bool
    {
        return $this === self::OnCoordination || $this === self::OnApproval;
    }

    /**
     * Из этих статусов рассматривающий отправляет документ на маршрут.
     */
    public function isSendable(): bool
    {
        return $this === self::InReview || $this === self::Recalled;
    }
}
