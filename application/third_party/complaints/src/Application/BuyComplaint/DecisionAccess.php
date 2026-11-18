<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use DateTimeImmutable;

/**
 * Общие проверки сценариев рассматривающего: блокировка решения,
 * право рассматривать, статус и закрепление за пользователем.
 */
final class DecisionAccess
{
    public function __construct(
        private readonly BuyComplaintRepository $decisions,
        private readonly AccessGuard $guard,
    ) {
    }

    /**
     * Блокирует решение и закрепляет его за рассматривающим, если оно свободно.
     *
     * @param list<BuyComplaintStatus> $statuses
     *
     * @throws AccessDenied решение закреплено за другим или нет прав
     * @throws StatusConflict
     */
    public function lockForReviewer(CurrentUser $user, int $decisionId, array $statuses, DateTimeImmutable $now): BuyComplaint
    {
        $decision = $this->decisions->lock($decisionId);
        $this->guard->assertAllowed($this->guard->canReview($user, $decision));
        $this->assertStatus($decision, $statuses);

        if (!$decision->hasReviewer()) {
            if (!$this->decisions->claim($decisionId, $user->userId, $now)) {
                throw new StatusConflict();
            }
            $decision = $this->decisions->lock($decisionId);
        }

        return $decision;
    }

    /**
     * @param list<BuyComplaintStatus> $statuses
     *
     * @throws StatusConflict
     */
    public function assertStatus(BuyComplaint $decision, array $statuses): void
    {
        if (!in_array($decision->status, $statuses, true)) {
            throw new StatusConflict();
        }
    }
}
