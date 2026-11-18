<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Pdf;

use InvalidArgumentException;

/**
 * Помощник шаблонов PDF: экранирование и двуязычные подписи «рус / қаз».
 * В шаблоне доступен как $doc, данные документа — как $v.
 */
final class DocumentTemplate
{
    /**
     * @param array<string, array{0: string, 1: string}> $labels ключ => [ru, kz]
     */
    public function __construct(
        private readonly array $labels,
    ) {
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Многострочный текст пользователя: экранирован, переводы строк сохранены.
     */
    public function multiline(mixed $value): string
    {
        return nl2br($this->e($value), false);
    }

    /**
     * Подпись на двух языках: «Номер жалобы / Шағымның нөмірі».
     */
    public function label(string $key): string
    {
        [$ru, $kz] = $this->pair($key);

        return $this->e($ru) . ' / <span class="kz">' . $this->e($kz) . '</span>';
    }

    /**
     * Значение на двух языках из массива ['ru' => ..., 'kz' => ...].
     *
     * @param array{ru?: string, kz?: string} $text
     */
    public function text(array $text): string
    {
        $ru = (string) ($text['ru'] ?? '');
        $kz = (string) ($text['kz'] ?? '');
        if ($kz === '' || $kz === $ru) {
            return $this->e($ru);
        }

        return $this->e($ru) . ' / <span class="kz">' . $this->e($kz) . '</span>';
    }

    /**
     * Подпись значения справочника: verdict.satisfied, decision_type.no_action, …
     */
    public function option(string $group, ?string $value): string
    {
        return $value === null || $value === '' ? '—' : $this->label($group . '.' . $value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function pair(string $key): array
    {
        return $this->labels[$key] ?? throw new InvalidArgumentException('Нет подписи шаблона: ' . $key);
    }
}
