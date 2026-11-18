<?php

declare(strict_types=1);

namespace Complaints\Domain\BuyComplaint;

use Complaints\Domain\Shared\InvalidStatusTransition;

/**
 * Разрешённые переходы статусов решения по жалобам.
 *
 *   in_review       -> on_coordination | on_approval         отправка рассматривающим
 *   on_coordination -> on_approval | recalled                подпись согласующего | отзыв
 *   on_approval     -> approved | recalled                   подпись утверждающего | отзыв
 *   recalled        -> on_coordination | on_approval         повторная отправка (новая версия)
 *   recalled        -> in_review                             правка содержимого
 */
final class BuyComplaintStateMachine
{
    private const TRANSITIONS = [
        'in_review' => ['on_coordination', 'on_approval'],
        'on_coordination' => ['on_approval', 'recalled'],
        'on_approval' => ['approved', 'recalled'],
        'recalled' => ['on_coordination', 'on_approval', 'in_review'],
        'approved' => [],
    ];

    public function canTransition(BuyComplaintStatus $from, BuyComplaintStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function assertCanTransition(BuyComplaintStatus $from, BuyComplaintStatus $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw InvalidStatusTransition::between($from, $to);
        }
    }
}
