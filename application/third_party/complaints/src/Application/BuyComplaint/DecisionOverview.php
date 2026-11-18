<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Port\UserCard;
use Complaints\Application\Repository\BuyCard;
use Complaints\Application\Repository\DecisionLotRow;
use Complaints\Application\View\DocumentView;
use Complaints\Application\View\SignatureView;
use Complaints\Domain\BuyComplaint\BuyComplaint;

/**
 * Решение по закупке: лоты, сводный документ, маршрут, подписи и действия.
 */
final readonly class DecisionOverview
{
    /**
     * @param list<DecisionLotRow> $lots
     * @param list<SignatureView>  $signatures подписи действующей версии сводного документа
     * @param list<DecisionGap>    $gaps       незаполненные лоты (ФЛК)
     * @param list<DecisionAction> $actions
     * @param list<UserCard>       $coordinatorCandidates
     * @param list<UserCard>       $approverCandidates
     */
    public function __construct(
        public BuyComplaint $decision,
        public BuyCard $buy,
        public OrganizationCard $organizer,
        public OrganizationCard $authority,
        public ?UserCard $reviewer,
        public ?UserCard $coordinator,
        public ?UserCard $approver,
        public array $lots,
        public ?DocumentView $consolidated,
        public array $signatures,
        public array $gaps,
        public array $actions,
        public array $coordinatorCandidates,
        public array $approverCandidates,
    ) {
    }

    public function allows(DecisionAction $action): bool
    {
        return in_array($action, $this->actions, true);
    }

    public function complaintsCount(): int
    {
        return array_sum(array_map(static fn (DecisionLotRow $row): int => $row->complaintsCount, $this->lots));
    }
}
