<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Persistence;

/**
 * Где хранятся PDF ответа и файлы к нему.
 *
 * Способ «по каждой жалобе» — app_complaint_docs у лота жалобы с этим ответом,
 * способ «одним ответом по лоту» — trd_complaint_docs у лота решения.
 */
final readonly class AnswerPlace
{
    /**
     * @param int|null $complaintId    жалоба лота с ответом; только при ответе по каждой жалобе
     * @param int|null $complaintLotId лот жалобы с ответом; null — ответ ещё не привязан
     */
    public function __construct(
        public bool $isPerComplaint,
        public int $decisionId,
        public int $decisionLotId,
        public ?int $complaintId,
        public ?int $complaintLotId,
    ) {
    }
}
