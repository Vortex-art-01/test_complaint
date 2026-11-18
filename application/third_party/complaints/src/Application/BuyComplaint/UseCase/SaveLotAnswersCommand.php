<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\Verdict;

/**
 * «Сохранить» на экране лота: вердикты, ответы и тип решения по лоту.
 */
final readonly class SaveLotAnswersCommand
{
    /**
     * @param array<int, ?Verdict> $verdicts      лот жалобы => вердикт
     * @param array<int, string>   $answerTexts   лот жалобы => текст ответа (per_complaint)
     * @param string|null          $lotAnswerText текст одного ответа на все жалобы лота (per_lot)
     */
    public function __construct(
        public int $decisionLotId,
        public array $verdicts,
        public array $answerTexts,
        public ?string $lotAnswerText,
        public ?DecisionType $decisionType,
    ) {
    }
}
