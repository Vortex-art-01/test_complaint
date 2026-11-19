<?php

declare(strict_types=1);

namespace Complaints\Application\Execution;

use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\LotDecisionAction;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\Document\DocumentType;

/**
 * Основание для действий над лотом в системе закупок: номер (id) и дата решения,
 * орган, подписанный сводный документ и номера жалоб по лоту.
 */
final class LotActions
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly AppComplaintRepository $complaints,
        private readonly OrganizationDirectory $directory,
    ) {
    }

    /**
     * @throws StatusConflict решение не утверждено или нет сводного документа
     */
    public function basis(BuyComplaint $decision, BuyComplaintLot $lot, int $actorUserId): LotDecisionAction
    {
        $document = $this->documents->current(DocumentType::ConsolidatedAnswer, $decision->id);
        if ($document === null || $decision->approvedAt === null) {
            throw new StatusConflict();
        }

        $numbers = [];
        foreach ($this->complaints->lotsOfBuyComplaintLot($lot->id) as $complaintLot) {
            $numbers[] = $this->complaints->get($complaintLot->complaintId)->number->value;
        }

        return new LotDecisionAction(
            $decision->buyId,
            $lot->buyLotId,
            $decision->id,
            $decision->approvedAt,
            $this->directory->organization($decision->authorityOrgId)->name->ru,
            $document->fileId,
            $numbers,
            $actorUserId,
        );
    }
}
