<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Shared\LocalizedText;
use DateTimeImmutable;

/**
 * Реквизиты жалобы: закупка, лот, организатор, поставщик, представитель,
 * руководитель. Фиксируются в requisites_snapshot при формировании PDF.
 */
final readonly class Requisites
{
    public function __construct(
        public string $buyNumber,
        public LocalizedText $buyName,
        public DateTimeImmutable $buyPublishedAt,
        public string $lotNumber,
        public LocalizedText $lotName,
        public string $organizerBin,
        public LocalizedText $organizerName,
        public LocalizedText $organizerAddress,
        public string $supplierBin,
        public LocalizedText $supplierName,
        public LocalizedText $supplierAddress,
        public string $representativeName,
        public string $representativeIin,
        public string $representativeEmail,
        public string $headName,
        public string $headPosition,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'buy' => [
                'number' => $this->buyNumber,
                'name' => self::text($this->buyName),
                'published_at' => $this->buyPublishedAt->format(DATE_ATOM),
            ],
            'lot' => ['number' => $this->lotNumber, 'name' => self::text($this->lotName)],
            'organizer' => [
                'bin' => $this->organizerBin,
                'name' => self::text($this->organizerName),
                'address' => self::text($this->organizerAddress),
            ],
            'supplier' => [
                'bin' => $this->supplierBin,
                'name' => self::text($this->supplierName),
                'address' => self::text($this->supplierAddress),
            ],
            'representative' => [
                'full_name' => $this->representativeName,
                'iin' => $this->representativeIin,
                'email' => $this->representativeEmail,
            ],
            'head' => ['full_name' => $this->headName, 'position' => $this->headPosition],
        ];
    }

    /**
     * @return array{ru: string, kz: string}
     */
    private static function text(LocalizedText $text): array
    {
        return ['ru' => $text->ru, 'kz' => $text->kz];
    }

    /**
     * @param array{ru?: string, kz?: string} $text
     */
    private static function localized(array $text): LocalizedText
    {
        return new LocalizedText((string) ($text['ru'] ?? ''), (string) ($text['kz'] ?? ''));
    }
}
