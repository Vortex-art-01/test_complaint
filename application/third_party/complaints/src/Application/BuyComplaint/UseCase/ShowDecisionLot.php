<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\BuyComplaint\DecisionLotDetails;
use Complaints\Application\BuyComplaint\LotAnswers;
use Complaints\Application\BuyComplaint\LotComplaintView;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Service\DocumentViews;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\BuyComplaint\DecisionType;
use Complaints\Domain\BuyComplaint\DecisionTypePolicy;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;

/**
 * Лот решения: жалобы лота с документами, вердикты, ответы, тип решения.
 */
final class ShowDecisionLot
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly BuyComplaintRepository $decisions,
        private readonly AppComplaintRepository $complaints,
        private readonly ProcurementRepository $procurement,
        private readonly OrganizationDirectory $directory,
        private readonly DocumentViews $views,
        private readonly LotAnswers $lotAnswers,
        private readonly DecisionTypePolicy $policy,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     */
    public function execute(int $decisionLotId): DecisionLotDetails
    {
        $user = $this->users->currentUser();
        $lot = $this->decisions->lot($decisionLotId);
        $decision = $this->decisions->get($lot->buyComplaintId);
        $this->guard->assertAllowed($this->guard->canViewDecision($user, $decision));

        $complaintLots = $this->lotAnswers->complaintLots($lot);
        $views = array_map(fn (AppComplaintLot $complaintLot): LotComplaintView => $this->complaintView($complaintLot), array_values($complaintLots));
        $verdicts = array_values(array_filter(array_map(static fn (AppComplaintLot $l) => $l->verdict, $complaintLots)));

        return new DecisionLotDetails(
            $decision,
            $lot,
            $this->procurement->buyLot($lot->buyLotId),
            $views,
            $decision->reviewMethod === ReviewMethod::PerLot
                ? $this->views->answer($this->lotAnswers->sharedAnswerId($complaintLots))
                : null,
            count($verdicts) === count($complaintLots) ? $this->policy->allowedTypes($verdicts) : DecisionType::cases(),
            $decision->status === BuyComplaintStatus::InReview
                && $decision->reviewMethod !== null
                && $this->guard->canReview($user, $decision),
        );
    }

    private function complaintView(AppComplaintLot $complaintLot): LotComplaintView
    {
        $complaint = $this->complaints->get($complaintLot->complaintId);

        return new LotComplaintView(
            $complaint,
            $complaintLot,
            $this->directory->organization($complaint->supplierOrgId),
            $this->views->current(DocumentType::AppComplaint, $complaint->id),
            $this->views->attachments(AttachmentOwner::AppComplaint, $complaint->id),
            $this->views->answer($complaintLot->answerId),
        );
    }
}
