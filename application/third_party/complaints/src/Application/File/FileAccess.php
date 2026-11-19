<?php

declare(strict_types=1);

namespace Complaints\Application\File;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Domain\BuyComplaint\BuyComplaintStatus;
use Complaints\Domain\Document\Attachment;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;

/**
 * Права на файлы модуля.
 *
 * Жалоба и её вложения — организации поставщика, органу и организатору.
 * Ответ и его вложения — органу, организатору и (после утверждения)
 * поставщику, чья жалоба получила этот ответ. Сводный документ и его подписи —
 * только органу и организатору: в нём ответы всем поставщикам закупки.
 */
final class FileAccess
{
    public function __construct(
        private readonly AccessGuard $guard,
        private readonly AppComplaintRepository $complaints,
        private readonly BuyComplaintRepository $decisions,
        private readonly AnswerRepository $answers,
    ) {
    }

    public function canReadDocument(CurrentUser $user, Document $document): bool
    {
        return match ($document->type) {
            DocumentType::AppComplaint => $this->canReadComplaint($user, $document->ownerId),
            DocumentType::Answer => $this->canReadAnswer($user, $document->ownerId),
            DocumentType::ConsolidatedAnswer => $this->guard->canViewDecision($user, $this->decisions->get($document->ownerId)),
        };
    }

    public function canReadAttachment(CurrentUser $user, Attachment $attachment): bool
    {
        return $attachment->owner === AttachmentOwner::AppComplaint
            ? $this->canReadComplaint($user, $attachment->ownerId)
            : $this->canReadAnswer($user, $attachment->ownerId);
    }

    private function canReadComplaint(CurrentUser $user, int $complaintId): bool
    {
        $complaint = $this->complaints->get($complaintId);
        $decision = $complaint->buyComplaintId === null ? null : $this->decisions->get($complaint->buyComplaintId);

        return $this->guard->canViewComplaint($user, $complaint, $decision);
    }

    private function canReadAnswer(CurrentUser $user, int $answerId): bool
    {
        $lot = $this->decisions->lot($this->answers->get($answerId)->buyComplaintLotId);
        $decision = $this->decisions->get($lot->buyComplaintId);
        if ($this->guard->canViewDecision($user, $decision)) {
            return true;
        }
        if ($decision->status !== BuyComplaintStatus::Approved) {
            return false;
        }

        foreach ($this->complaints->lotsOfBuyComplaintLot($lot->id) as $complaintLot) {
            if ($complaintLot->answerId === $answerId
                && $user->belongsTo($this->complaints->get($complaintLot->complaintId)->supplierOrgId)) {
                return true;
            }
        }

        return false;
    }
}
