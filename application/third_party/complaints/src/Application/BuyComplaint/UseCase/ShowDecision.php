<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\ModuleRole;
use Complaints\Application\BuyComplaint\DecisionAction;
use Complaints\Application\BuyComplaint\DecisionActions;
use Complaints\Application\BuyComplaint\DecisionCompleteness;
use Complaints\Application\BuyComplaint\DecisionOverview;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\AuthorityResolver;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\UserCard;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ComplaintQueries;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Service\DocumentViews;
use Complaints\Application\View\SignatureView;
use Complaints\Domain\Document\DocumentType;

/**
 * Решение по закупке для органа и организатора.
 */
final class ShowDecision
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly BuyComplaintRepository $decisions,
        private readonly ComplaintQueries $queries,
        private readonly ProcurementRepository $procurement,
        private readonly OrganizationDirectory $directory,
        private readonly AuthorityResolver $authorities,
        private readonly DocumentViews $views,
        private readonly DecisionCompleteness $completeness,
        private readonly DecisionActions $actions,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     */
    public function execute(int $decisionId): DecisionOverview
    {
        $user = $this->users->currentUser();
        $decision = $this->decisions->get($decisionId);
        $this->guard->assertAllowed($this->guard->canViewDecision($user, $decision));

        $buy = $this->procurement->buy($decision->buyId);
        $consolidated = $this->views->current(DocumentType::ConsolidatedAnswer, $decisionId);
        $signatures = $consolidated === null ? [] : $this->views->signatures($consolidated->document);
        $gaps = $decision->reviewMethod === null ? [] : $this->completeness->gaps($decisionId);
        $actions = $this->actions->available(
            $user,
            $decision,
            $this->decisions->lots($decisionId),
            $decision->reviewMethod === null || $gaps !== [],
            $consolidated?->document,
            $this->isUnsigned($signatures),
        );
        $canSend = in_array(DecisionAction::Send, $actions, true);

        return new DecisionOverview(
            $decision,
            $buy,
            $this->directory->organization($buy->organizerOrgId),
            $this->directory->organization($decision->authorityOrgId),
            $this->user($decision->reviewerUserId),
            $this->user($decision->coordinatorUserId),
            $this->user($decision->approverUserId),
            $this->queries->decisionLots($decisionId),
            $consolidated,
            $signatures,
            $gaps,
            $actions,
            $canSend ? $this->colleagues($decision->authorityOrgId, ModuleRole::Coordinator, $user->userId) : [],
            $canSend ? $this->colleagues($decision->authorityOrgId, ModuleRole::Approver, $user->userId) : [],
        );
    }

    /**
     * @param list<SignatureView> $signatures
     */
    private function isUnsigned(array $signatures): bool
    {
        foreach ($signatures as $view) {
            if ($view->signature->isSigned()) {
                return false;
            }
        }

        return true;
    }

    private function user(?int $userId): ?UserCard
    {
        return $userId === null ? null : $this->directory->user($userId);
    }

    /**
     * @return list<UserCard>
     */
    private function colleagues(int $authorityOrgId, ModuleRole $role, int $exceptUserId): array
    {
        return array_values(array_filter(
            $this->authorities->employees($authorityOrgId, $role),
            static fn (UserCard $card): bool => $card->id !== $exceptUserId,
        ));
    }
}
