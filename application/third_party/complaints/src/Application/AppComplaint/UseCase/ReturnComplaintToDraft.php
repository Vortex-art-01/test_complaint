<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

use Complaints\Application\AppComplaint\DraftAccess;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Exception\ValidationFailed;
use Complaints\Application\Port\Clock;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\TransactionManager;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Application\Service\StatusTransitioner;
use Complaints\Domain\AppComplaint\AppComplaintStatus;
use Complaints\Domain\Document\DocumentType;

/**
 * «Назад» с экрана подписи или «Редактировать» отозванную жалобу:
 * awaiting_signature | signed | withdrawn -> draft. Версия PDF перестаёт
 * действовать, подпись руководителя аннулируется, реквизиты снова живые.
 */
final class ReturnComplaintToDraft
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly DraftAccess $access,
        private readonly AppComplaintRepository $complaints,
        private readonly DocumentIssuer $documents,
        private readonly StatusTransitioner $transitions,
        private readonly TransactionManager $transactions,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @throws ValidationFailed срок обжалования закрыт
     * @throws AccessDenied
     * @throws StatusConflict
     */
    public function execute(int $complaintId): void
    {
        $user = $this->users->currentUser();
        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($user, $complaintId, $now): void {
            $complaint = $this->access->lockForAuthor(
                $user,
                $complaintId,
                [AppComplaintStatus::AwaitingSignature, AppComplaintStatus::Signed, AppComplaintStatus::Withdrawn],
                $now,
            );

            $this->documents->retire(DocumentType::AppComplaint, $complaintId, $user->userId, $now);
            $this->complaints->saveRequisitesSnapshot($complaintId, null, $now);
            $this->transitions->moveComplaint($complaint, AppComplaintStatus::Draft, $user->userId, $now);
        });
    }
}
