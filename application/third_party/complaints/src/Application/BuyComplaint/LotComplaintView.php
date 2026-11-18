<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\View\AnswerView;
use Complaints\Application\View\AttachmentView;
use Complaints\Application\View\DocumentView;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;

/**
 * Жалоба в лоте решения: суть, документы поставщика, вердикт и ответ.
 */
final readonly class LotComplaintView
{
    /**
     * @param list<AttachmentView> $attachments
     */
    public function __construct(
        public AppComplaint $complaint,
        public AppComplaintLot $complaintLot,
        public OrganizationCard $supplier,
        public ?DocumentView $complaintDocument,
        public array $attachments,
        public ?AnswerView $answer,
    ) {
    }
}
