<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\BuyComplaint\DecisionAccess;
use Complaints\Application\BuyComplaint\LotAnswers;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationErrors;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\TransactionRunner;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\Answer;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\DecisionTypePolicy;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

/**
 * Сохраняет вердикты, ответы и тип решения по лоту.
 *
 * Ответ со сформированным PDF не меняется до «Сбросить». Если все жалобы лота
 * отклонены, тип решения ставится «без изменений» автоматически.
 * Любое изменение снимает с действия сводный документ.
 */
final class SaveLotAnswers
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DecisionAccess $access,
        private readonly BuyComplaintRepository $decisions,
        private readonly AppComplaintRepository $complaints,
        private readonly AnswerRepository $answers,
        private readonly LotAnswers $lotAnswers,
        private readonly DecisionTypePolicy $policy,
        private readonly DocumentIssuer $documents,
        private readonly TransactionRunner $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(SaveLotAnswersCommand $command): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $command, $now): void {
            $lot = $this->decisions->lot($command->decisionLotId);
            $decision = $this->access->lockForReviewer($user, $lot->buyComplaintId, [BuyComplaintStatus::InReview], $now);
            if ($decision->reviewMethod === null) {
                throw ValidationFailed::field('form', 'complaints_error_method_required');
            }

            $complaintLots = $this->lotAnswers->complaintLots($lot);
            foreach (array_keys($command->verdicts + $command->answerTexts) as $complaintLotId) {
                if (!isset($complaintLots[$complaintLotId])) {
                    throw ValidationFailed::field('form', 'complaints_error_invalid_input');
                }
            }

            $errors = new ValidationErrors();
            if ($decision->reviewMethod === ReviewMethod::PerComplaint) {
                foreach ($complaintLots as $complaintLotId => $complaintLot) {
                    $this->savePerComplaint(
                        $complaintLot,
                        $command->verdicts[$complaintLotId] ?? null,
                        trim($command->answerTexts[$complaintLotId] ?? ''),
                        $lot,
                        $user,
                        $now,
                        $errors,
                    );
                }
            } else {
                $this->savePerLot($complaintLots, $command->verdicts, trim((string) $command->lotAnswerText), $lot, $user, $now, $errors);
            }
            $errors->throwIfAny();

            $this->saveDecisionType($lot, $command->decisionType, $now);
            $this->documents->retire(DocumentType::ConsolidatedAnswer, $decision->id, $now);
        });
    }

    private function savePerComplaint(
        AppComplaintLot $complaintLot,
        ?Verdict $verdict,
        string $text,
        BuyComplaintLot $lot,
        CurrentUser $user,
        DateTimeImmutable $now,
        ValidationErrors $errors,
    ): void {
        $field = 'answers.' . $complaintLot->id;
        if ($this->lotAnswers->isFormed($complaintLot->answerId)) {
            $current = trim($this->answers->get((int) $complaintLot->answerId)->text);
            if ($verdict !== $complaintLot->verdict || $text !== $current) {
                $errors->add($field, 'complaints_error_answer_formed');
            }

            return;
        }
        if (mb_strlen($text) > Answer::MAX_LENGTH) {
            $errors->add($field, 'complaints_error_answer_length', ['max' => (string) Answer::MAX_LENGTH]);

            return;
        }

        $this->complaints->setVerdict($complaintLot->id, $verdict, $now);
        if ($complaintLot->answerId !== null) {
            $this->answers->updateText($complaintLot->answerId, $text, $now);
        } elseif ($text !== '') {
            $answerId = $this->answers->create($lot->id, $text, $now);
            $this->complaints->setAnswer($complaintLot->id, $answerId, $now);
        }
    }

    /**
     * @param array<int, AppComplaintLot> $complaintLots
     * @param array<int, ?Verdict>        $verdicts
     */
    private function savePerLot(
        array $complaintLots,
        array $verdicts,
        string $text,
        BuyComplaintLot $lot,
        CurrentUser $user,
        DateTimeImmutable $now,
        ValidationErrors $errors,
    ): void {
        $answerId = $this->lotAnswers->sharedAnswerId($complaintLots);
        if ($this->lotAnswers->isFormed($answerId)) {
            $changed = trim($this->answers->get((int) $answerId)->text) !== $text;
            foreach ($complaintLots as $complaintLotId => $complaintLot) {
                $changed = $changed || ($verdicts[$complaintLotId] ?? null) !== $complaintLot->verdict;
            }
            if ($changed) {
                $errors->add('lot_answer', 'complaints_error_answer_formed');
            }

            return;
        }
        if (mb_strlen($text) > Answer::MAX_LENGTH) {
            $errors->add('lot_answer', 'complaints_error_answer_length', ['max' => (string) Answer::MAX_LENGTH]);

            return;
        }

        foreach ($complaintLots as $complaintLotId => $complaintLot) {
            $this->complaints->setVerdict($complaintLot->id, $verdicts[$complaintLotId] ?? null, $now);
        }
        if ($answerId !== null) {
            $this->answers->updateText($answerId, $text, $now);
        } elseif ($text !== '') {
            $answerId = $this->answers->create($lot->id, $text, $now);
        }
        foreach ($complaintLots as $complaintLot) {
            if ($answerId !== null && $complaintLot->answerId !== $answerId) {
                $this->complaints->setAnswer($complaintLot->id, $answerId, $now);
            }
        }
    }

    /**
     * @throws ValidationFailed тип решения не согласуется с вердиктами
     */
    private function saveDecisionType(BuyComplaintLot $lot, ?DecisionType $requested, DateTimeImmutable $now): void
    {
        $verdicts = [];
        $complete = true;
        foreach ($this->lotAnswers->complaintLots($lot) as $complaintLot) {
            if ($complaintLot->verdict === null) {
                $complete = false;
            } else {
                $verdicts[] = $complaintLot->verdict;
            }
        }

        $type = $requested;
        if ($complete) {
            $forced = $this->policy->forcedType($verdicts);
            if ($forced !== null) {
                $type = $forced;
            } elseif ($type !== null && !$this->policy->isAllowed($type, $verdicts)) {
                throw ValidationFailed::field('decision_type', 'complaints_error_decision_type_not_allowed');
            }
        }

        $this->decisions->setDecisionType($lot->id, $type, $now);
    }
}
