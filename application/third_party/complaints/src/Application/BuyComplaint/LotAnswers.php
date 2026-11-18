<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

/**
 * Ответы лота решения: per_complaint — свой ответ у каждого лота жалобы,
 * per_lot — один ответ, связанный со всеми лотами жалоб лота закупки.
 */
final class LotAnswers
{
    public function __construct(
        private readonly AnswerRepository $answers,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentRepository $documents,
    ) {
    }

    /**
     * Лоты жалоб, собранные в лот решения, по id.
     *
     * @return array<int, AppComplaintLot>
     */
    public function complaintLots(BuyComplaintLot $lot): array
    {
        $byId = [];
        foreach ($this->complaints->lotsOfBuyComplaintLot($lot->id) as $complaintLot) {
            $byId[$complaintLot->id] = $complaintLot;
        }

        return $byId;
    }

    /**
     * Ответ, к которому относится лот жалобы (per_complaint) или лот целиком (per_lot);
     * создаёт пустой ответ, если его ещё нет.
     *
     * @throws ValidationFailed лот жалобы не указан или не из этого лота
     */
    public function ensureAnswer(
        BuyComplaint $decision,
        BuyComplaintLot $lot,
        ?int $complaintLotId,
        int $userId,
        DateTimeImmutable $now,
    ): int {
        $complaintLots = $this->complaintLots($lot);
        $targets = $decision->reviewMethod === ReviewMethod::PerLot
            ? array_values($complaintLots)
            : [$complaintLots[$complaintLotId ?? 0] ?? throw ValidationFailed::field('complaint_lot_id', 'complaints_error_invalid_input')];

        foreach ($targets as $complaintLot) {
            if ($complaintLot->answerId !== null) {
                return $complaintLot->answerId;
            }
        }

        $answerId = $this->answers->create($lot->id, '', $userId, $now);
        foreach ($targets as $complaintLot) {
            $this->complaints->setAnswer($complaintLot->id, $answerId, $now);
        }

        return $answerId;
    }

    /**
     * Ответ лота целиком (per_lot); null — ещё не создан.
     *
     * @param array<int, AppComplaintLot> $complaintLots
     */
    public function sharedAnswerId(array $complaintLots): ?int
    {
        foreach ($complaintLots as $complaintLot) {
            if ($complaintLot->answerId !== null) {
                return $complaintLot->answerId;
            }
        }

        return null;
    }

    /**
     * PDF ответа сформирован — текст, вердикты и файлы менять нельзя до «Сбросить».
     */
    public function isFormed(?int $answerId): bool
    {
        return $answerId !== null && $this->documents->current(DocumentType::Answer, $answerId) !== null;
    }

    /**
     * @throws ValidationFailed
     */
    public function assertNotFormed(?int $answerId, string $field): void
    {
        if ($this->isFormed($answerId)) {
            throw ValidationFailed::field($field, 'complaints_error_answer_formed');
        }
    }
}
