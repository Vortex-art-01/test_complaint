<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use DateTimeImmutable;

/**
 * Основание для действия над лотом в системе закупок: утверждённое решение (его номер — id)
 * по жалобам. Поля формы отмены (макет 3.5) заполняются из него.
 */
final readonly class LotDecisionAction
{
    /**
     * @param list<string> $complaintNumbers номера жалоб по лоту, для баннера пересмотра
     */
    public function __construct(
        public int $buyId,
        public int $buyLotId,
        public int $decisionId,
        public DateTimeImmutable $decisionDate,
        public string $authorityName,
        public int $basisFileId,
        public array $complaintNumbers,
        public int $actorUserId,
    ) {
    }
}
