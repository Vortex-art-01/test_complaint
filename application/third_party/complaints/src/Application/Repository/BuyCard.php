<?php

declare(strict_types=1);

namespace Complaints\Application\Repository;

use Complaints\Application\Shared\LocalizedText;
use Complaints\Domain\Shared\Period;
use DateTimeImmutable;

/**
 * Закупка проекта: то, что нужно модулю.
 */
final readonly class BuyCard
{
    /**
     * @param Period|null $appealPeriod срок обжалования, который проект устанавливает
     *                                  при публикации протокола итогов; null — не установлен
     */
    public function __construct(
        public int $id,
        public string $number,
        public LocalizedText $name,
        public int $methodId,
        public int $organizerOrgId,
        public DateTimeImmutable $publishedAt,
        public ?Period $appealPeriod,
    ) {
    }

    public function isAppealOpenAt(DateTimeImmutable $moment): bool
    {
        return $this->appealPeriod !== null && $this->appealPeriod->isOpenAt($moment);
    }
}
