<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Repository\BuyLotCard;
use Complaints\Application\View\AnswerView;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\DecisionType;

/**
 * Лот решения: жалобы лота, ответы и тип решения.
 */
final readonly class DecisionLotDetails
{
    /**
     * @param list<LotComplaintView> $complaints
     * @param list<DecisionType>     $allowedDecisionTypes с учётом вердиктов
     */
    public function __construct(
        public BuyComplaint $decision,
        public BuyComplaintLot $lot,
        public BuyLotCard $buyLot,
        public array $complaints,
        public ?AnswerView $lotAnswer,
        public array $allowedDecisionTypes,
        public bool $editable,
    ) {
    }
}
