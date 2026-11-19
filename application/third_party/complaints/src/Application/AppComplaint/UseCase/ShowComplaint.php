<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\AppComplaint\ComplaintActions;
use Complaints\Application\AppComplaint\ComplaintAnswer;
use Complaints\Application\AppComplaint\ComplaintCard;
use Complaints\Application\AppComplaint\ComplaintRequisites;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Service\DocumentViews;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\Verdict;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\DocumentType;

/**
 * Просмотр жалобы: сотрудникам поставщика, органу и организатору решения.
 */
final class ShowComplaint
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
        private readonly DocumentRepository $documents,
        private readonly ComplaintRequisites $requisites,
        private readonly ComplaintActions $actions,
        private readonly DocumentViews $views,
        private readonly OrganizationDirectory $directory,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     */
    public function execute(int $complaintId): ComplaintCard
    {
        $user = $this->users->currentUser();
        $complaint = $this->complaints->get($complaintId);
        $decision = $complaint->buyComplaintId === null ? null : $this->decisions->get($complaint->buyComplaintId);
        $this->guard->assertAllowed($this->guard->canViewComplaint($user, $complaint, $decision));

        $now = $this->clock->now();
        $lot = $this->complaints->lotOf($complaintId);
        $document = $this->views->current(DocumentType::AppComplaint, $complaintId);

        return new ComplaintCard(
            $complaint,
            $lot,
            $this->requisites->current($complaint, $lot),
            $this->views->attachments(AttachmentOwner::AppComplaint, $complaintId),
            $document,
            $document === null ? [] : $this->views->signatures($document->document),
            $this->actions->available($user, $complaint, $now),
            $complaint->isAppealOpenAt($now),
            $complaint->status === AppComplaintStatus::Reviewed && $decision !== null
                ? $this->answer($lot, $decision)
                : null,
        );
    }

    private function answer(AppComplaintLot $lot, BuyComplaint $decision): ?ComplaintAnswer
    {
        $answer = $this->views->answer($lot->answerId);
        if ($answer === null || $lot->verdict === null || $lot->buyComplaintLotId === null) {
            return null;
        }

        $decisionLot = $this->decisions->lot($lot->buyComplaintLotId);
        $consolidated = $this->documents->current(DocumentType::ConsolidatedAnswer, $decision->id);

        return new ComplaintAnswer(
            $lot->verdict,
            $lot->verdict === Verdict::Satisfied ? $decisionLot->decisionType : null,
            $answer,
            $this->directory->organization($decision->authorityOrgId),
            $decision->reviewerUserId === null ? '' : $this->directory->user($decision->reviewerUserId)->fullName,
            $decision->reviewPeriod,
            $decision->id,
            $decision->approvedAt ?? $decision->reviewPeriod->startsAt,
            $consolidated === null ? [] : $this->views->signatures($consolidated),
        );
    }
}
