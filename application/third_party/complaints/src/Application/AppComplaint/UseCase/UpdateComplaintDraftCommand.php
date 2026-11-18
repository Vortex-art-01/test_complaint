<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint\UseCase;

/**
 * Поля «Суть жалобы» из формы черновика.
 */
final readonly class UpdateComplaintDraftCommand
{
    public function __construct(
        public int $complaintId,
        public ?string $contactPhone,
        public ?string $description,
    ) {
    }
}
