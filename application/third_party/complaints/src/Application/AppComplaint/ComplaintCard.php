<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\View\AttachmentView;
use Complaints\Application\View\DocumentView;
use Complaints\Application\View\SignatureView;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\AppComplaint\AppComplaintLot;

/**
 * Жалоба целиком: форма, подписание, предпросмотр, просмотр и ответ.
 */
final readonly class ComplaintCard
{
    /**
     * @param list<AttachmentView>  $attachments
     * @param list<SignatureView>   $signatures подписи действующей версии PDF
     * @param list<ComplaintAction> $actions
     */
    public function __construct(
        public AppComplaint $complaint,
        public AppComplaintLot $lot,
        public Requisites $requisites,
        public array $attachments,
        public ?DocumentView $document,
        public array $signatures,
        public array $actions,
        public bool $appealOpen,
        public ?ComplaintAnswer $answer,
    ) {
    }

    public function allows(ComplaintAction $action): bool
    {
        return in_array($action, $this->actions, true);
    }
}
