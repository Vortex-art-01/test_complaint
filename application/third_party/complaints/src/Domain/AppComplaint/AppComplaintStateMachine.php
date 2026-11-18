<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

use Complaints\Domain\Shared\InvalidStatusTransition;

/**
 * Разрешённые переходы статусов жалобы поставщика.
 *
 *   draft              -> awaiting_signature        автор: «Далее» на форме
 *   awaiting_signature -> signed | draft            руководитель подписал | автор вернул
 *   signed             -> submitted | draft         подача | автор вернул
 *   submitted          -> withdrawn | in_review     отзыв автора | сбор жалоб
 *   withdrawn          -> submitted | draft         повторная подача | правка
 *   in_review          -> reviewed                  утверждение решения
 */
final class AppComplaintStateMachine
{
    private const TRANSITIONS = [
        'draft' => ['awaiting_signature'],
        'awaiting_signature' => ['signed', 'draft'],
        'signed' => ['submitted', 'draft'],
        'submitted' => ['withdrawn', 'in_review'],
        'withdrawn' => ['submitted', 'draft'],
        'in_review' => ['reviewed'],
        'reviewed' => [],
    ];

    public function canTransition(AppComplaintStatus $from, AppComplaintStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function assertCanTransition(AppComplaintStatus $from, AppComplaintStatus $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw InvalidStatusTransition::between($from, $to);
        }
    }
}
