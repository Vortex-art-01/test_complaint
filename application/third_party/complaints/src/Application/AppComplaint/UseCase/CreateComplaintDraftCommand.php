<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

/**
 * Выбор шагов 1–3 мастера: закупка, лот заявки, руководитель.
 */
final readonly class CreateComplaintDraftCommand
{
    public function __construct(
        public int $buyId,
        public int $appLotId,
        public int $headUserId,
    ) {
    }
}
