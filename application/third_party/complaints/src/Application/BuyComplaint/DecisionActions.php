<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus as Status;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\ExecutionStatus;
use Complaints\Domain\Document\Document;

/**
 * Какие действия над решением доступны пользователю сейчас.
 * Сценарии проверяют то же самое на сервере перед изменением.
 */
final class DecisionActions
{
    public function __construct(
        private readonly AccessGuard $guard,
    ) {
    }

    /**
     * @param list<BuyComplaintLot> $lots
     * @param bool                  $consolidatedIsFresh сводный документ сформирован и ещё не подписывался
     * @return list<DecisionAction>
     */
    public function available(
        CurrentUser $user,
        BuyComplaint $decision,
        array $lots,
        bool $hasGaps,
        ?Document $consolidated,
        bool $consolidatedIsFresh,
    ): array {
        $reviewer = $this->guard->canReview($user, $decision);
        $status = $decision->status;
        $methodChosen = $decision->reviewMethod !== null;
        $organizer = $this->guard->isOrganizerOf($user, $decision)
            && $decision->executionStatus === ExecutionStatus::InProgress;

        $rules = [
            DecisionAction::ChooseMethod->value => $reviewer && $status === Status::InReview,
            DecisionAction::EditAnswers->value => $reviewer && $status === Status::InReview && $methodChosen,
            DecisionAction::FormConsolidated->value => $reviewer && $status->isSendable() && $methodChosen && !$hasGaps,
            DecisionAction::Send->value => $reviewer && $status->isSendable() && !$hasGaps
                && $consolidated !== null && $consolidatedIsFresh,
            DecisionAction::Recall->value => $reviewer && $status->isOnRoute(),
            DecisionAction::Reopen->value => $reviewer && $status === Status::Recalled,
            DecisionAction::Release->value => $this->guard->canRelease($user, $decision) && $status->isSendable(),
            DecisionAction::Coordinate->value => $status === Status::OnCoordination && $this->guard->canCoordinate($user, $decision),
            DecisionAction::Approve->value => $status === Status::OnApproval && $this->guard->canApprove($user, $decision),
            DecisionAction::CancelLots->value => $organizer && self::awaits($lots, DecisionType::CancelPurchase),
            DecisionAction::StartRevision->value => $organizer && self::awaits($lots, DecisionType::ReviseResults),
        ];

        return array_values(array_map(
            static fn (string $action): DecisionAction => DecisionAction::from($action),
            array_keys(array_filter($rules)),
        ));
    }

    /**
     * @param list<BuyComplaintLot> $lots
     */
    private static function awaits(array $lots, DecisionType $type): bool
    {
        foreach ($lots as $lot) {
            if ($lot->isAwaitingExecution($type)) {
                return true;
            }
        }

        return false;
    }
}
