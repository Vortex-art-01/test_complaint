<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

/**
 * «Отправить на согласование»: маршрут и подпись рассматривающего.
 */
final readonly class SendDecisionCommand
{
    /**
     * @param int|null $coordinatorUserId согласующий; null — сразу утверждающему
     * @param string   $signatureBase64   отсоединённая CMS-подпись сводного документа
     */
    public function __construct(
        public int $decisionId,
        public ?int $coordinatorUserId,
        public int $approverUserId,
        public string $signatureBase64,
    ) {
    }
}
