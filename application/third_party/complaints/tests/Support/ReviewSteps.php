<?php

declare(strict_types=1);

namespace Complaints\Tests\Support;

use Complaints\Application\BuyComplaint\UseCase\ApproveDecision;
use Complaints\Application\BuyComplaint\UseCase\ChooseReviewMethod;
use Complaints\Application\BuyComplaint\UseCase\CoordinateDecision;
use Complaints\Application\BuyComplaint\UseCase\FormAnswerDocument;
use Complaints\Application\BuyComplaint\UseCase\FormConsolidatedDocument;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswers;
use Complaints\Application\BuyComplaint\UseCase\SaveLotAnswersCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionCommand;
use Complaints\Application\BuyComplaint\UseCase\SendDecisionForApproval;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\DocumentType;
use LogicException;

/**
 * Шаги рассмотрения для тестов: от собранных жалоб до утверждённого решения.
 * Состояние читается через интерфейсы репозиториев — шаги работают
 * и на репозиториях в памяти, и на SQL-репозиториях.
 */
final class ReviewSteps
{
    public function __construct(
        private readonly World $world,
    ) {
    }

    private function complaints(): AppComplaintRepository
    {
        return $this->world->get(AppComplaintRepository::class);
    }

    private function decisions(): BuyComplaintRepository
    {
        return $this->world->get(BuyComplaintRepository::class);
    }

    private function documents(): DocumentRepository
    {
        return $this->world->get(DocumentRepository::class);
    }

    /**
     * Решение, в которое собрана жалоба.
     */
    public function decisionOf(int $complaintId): int
    {
        return $this->complaints()->get($complaintId)->buyComplaintId
            ?? throw new LogicException('Жалоба ' . $complaintId . ' ещё не собрана.');
    }

    /**
     * Лот решения, в который собрана жалоба.
     */
    public function decisionLotOf(int $complaintId): int
    {
        return $this->complaints()->lotOf($complaintId)->buyComplaintLotId
            ?? throw new LogicException('Жалоба ' . $complaintId . ' ещё не собрана.');
    }

    public function complaintLotOf(int $complaintId): int
    {
        return $this->complaints()->lotOf($complaintId)->id;
    }

    public function answerOf(int $complaintId): ?int
    {
        return $this->complaints()->lotOf($complaintId)->answerId;
    }

    public function chooseMethod(int $decisionId, ReviewMethod $method = ReviewMethod::PerComplaint): void
    {
        $this->world->actAs(World::REVIEWER);
        $this->world->get(ChooseReviewMethod::class)->execute($decisionId, $method);
    }

    /**
     * Вердикты и ответы способом per_complaint по жалобам одного лота решения.
     *
     * @param array<int, Verdict> $verdicts жалоба => вердикт
     */
    public function answer(array $verdicts, ?DecisionType $type = null): void
    {
        $this->world->actAs(World::REVIEWER);
        $complaintIds = array_keys($verdicts);
        $byLot = [];
        $texts = [];
        foreach ($verdicts as $complaintId => $verdict) {
            $byLot[$this->complaintLotOf($complaintId)] = $verdict;
            $texts[$this->complaintLotOf($complaintId)] = 'Ответ по жалобе ' . $complaintId . ': ' . $verdict->value;
        }

        $this->world->get(SaveLotAnswers::class)->execute(new SaveLotAnswersCommand(
            $this->decisionLotOf($complaintIds[0]),
            $byLot,
            $texts,
            null,
            $type,
        ));
    }

    /**
     * «Сформировать» PDF для ответов, у которых его ещё нет.
     *
     * @param list<int> $complaintIds
     */
    public function formAnswers(array $complaintIds): void
    {
        $this->world->actAs(World::REVIEWER);
        foreach ($complaintIds as $complaintId) {
            $answerId = $this->answerOf($complaintId) ?? throw new LogicException('Нет ответа на жалобу ' . $complaintId);
            if ($this->documents()->current(DocumentType::Answer, $answerId) === null) {
                $this->world->get(FormAnswerDocument::class)->execute($answerId);
            }
        }
    }

    /**
     * Сводный документ и отправка по маршруту.
     */
    public function send(int $decisionId, ?int $coordinator = World::COORDINATOR, int $approver = World::APPROVER): void
    {
        $this->world->actAs(World::REVIEWER);
        $this->world->get(FormConsolidatedDocument::class)->execute($decisionId);
        $this->world->get(SendDecisionForApproval::class)->execute(
            new SendDecisionCommand($decisionId, $coordinator, $approver, base64_encode('cms-reviewer')),
        );
    }

    /**
     * Согласование (если есть согласующий) и утверждение.
     */
    public function approve(int $decisionId): void
    {
        if ($this->decisions()->get($decisionId)->coordinatorUserId !== null) {
            $this->world->actAs(World::COORDINATOR);
            $this->world->get(CoordinateDecision::class)->execute($decisionId, base64_encode('cms-coordinator'));
        }
        $this->world->actAs(World::APPROVER);
        $this->world->get(ApproveDecision::class)->execute($decisionId, base64_encode('cms-approver'));
    }

    /**
     * Ответы по всем лотам решения: жалобы группируются по лотам решения.
     *
     * @param array<int, Verdict>      $verdicts жалоба => вердикт
     * @param array<int, DecisionType> $types    лот закупки => тип решения
     */
    public function answerAll(array $verdicts, array $types = []): void
    {
        $byLot = [];
        foreach ($verdicts as $complaintId => $verdict) {
            $byLot[$this->decisionLotOf($complaintId)][$complaintId] = $verdict;
        }
        foreach ($byLot as $decisionLotId => $lotVerdicts) {
            $this->answer($lotVerdicts, $types[$this->decisions()->lot($decisionLotId)->buyLotId] ?? null);
        }
        $this->formAnswers(array_keys($verdicts));
    }

    /**
     * Полный цикл: способ, ответы и PDF по всем лотам, маршрут, утверждение.
     *
     * @param array<int, Verdict>      $verdicts жалоба => вердикт (все жалобы решения)
     * @param array<int, DecisionType> $types    лот закупки => тип решения
     */
    public function decide(array $verdicts, array $types = []): int
    {
        $decisionId = $this->decisionOf(array_key_first($verdicts));
        if ($this->decisions()->get($decisionId)->reviewMethod === null) {
            $this->chooseMethod($decisionId);
        }
        $this->answerAll($verdicts, $types);
        $this->send($decisionId);
        $this->approve($decisionId);

        return $decisionId;
    }
}
