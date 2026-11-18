<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\View\AnswerView;
use Complaints\Application\View\SignatureView;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Вкладка «Ответ на жалобу»: решение, орган, ответ и подписи сводного документа.
 * Тип решения показывается только при удовлетворённой жалобе.
 */
final readonly class ComplaintAnswer
{
    /**
     * @param list<SignatureView> $signers подписанты сводного документа; файлы подписей поставщику не выдаются
     */
    public function __construct(
        public Verdict $verdict,
        public ?DecisionType $decisionType,
        public AnswerView $answer,
        public OrganizationCard $authority,
        public string $reviewerName,
        public Period $reviewPeriod,
        public string $decisionNumber,
        public DateTimeImmutable $approvedAt,
        public array $signers,
    ) {
    }
}
