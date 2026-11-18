<?php

declare(strict_types=1);

namespace Complaints\Domain\AppComplaint;

use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Жалоба поставщика по своей заявке (снимок строки test_app_complaint).
 */
final readonly class AppComplaint
{
    /**
     * @param array<string, mixed>|null $requisitesSnapshot реквизиты на момент формирования PDF
     */
    public function __construct(
        public int $id,
        public ComplaintNumber $number,
        public int $buyId,
        public int $appId,
        public ?int $buyComplaintId,
        public int $supplierOrgId,
        public int $authorUserId,
        public int $headUserId,
        public ?string $contactPhone,
        public ?string $description,
        public ?array $requisitesSnapshot,
        public DateTimeImmutable $protocolPublishedAt,
        public Period $appealPeriod,
        public AppComplaintStatus $status,
        public ?DateTimeImmutable $submittedAt,
        public ?DateTimeImmutable $withdrawnAt,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $deletedAt,
    ) {
    }

    public function isAuthoredBy(int $userId): bool
    {
        return $this->authorUserId === $userId;
    }

    public function isHeadedBy(int $userId): bool
    {
        return $this->headUserId === $userId;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function isAppealOpenAt(DateTimeImmutable $moment): bool
    {
        return $this->appealPeriod->isOpenAt($moment);
    }

    /**
     * Телефон и описание заполнены — без них жалобу не отправить на подпись.
     */
    public function isFilled(): bool
    {
        return trim((string) $this->contactPhone) !== '' && trim((string) $this->description) !== '';
    }
}
