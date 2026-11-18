<?php

defined('BASEPATH') or exit('No direct script access allowed');

use Complaints\Application\Shared\LocalizedText;

/*
 * Помощники представлений модуля «Жалобы»: $this->load->helper('complaints').
 * Представления модуля обращаются к CodeIgniter только через эти функции.
 */

if (!function_exists('complaints_language')) {
    /**
     * Язык интерфейса из первого сегмента адреса: 'ru' или 'kz'.
     */
    function complaints_language(): string
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса, если язык интерфейса проект
        // определяет не по первому сегменту адреса (/ru/…, /kz/…), а своей библиотекой.
        return get_instance()->uri->segment(1) === 'kz' ? 'kz' : 'ru';
    }
}

if (!function_exists('complaints_t')) {
    /**
     * Строка языкового файла с подстановкой {параметров}.
     *
     * @param array<string, scalar> $params
     */
    function complaints_t(string $key, array $params = []): string
    {
        $text = get_instance()->lang->line($key);
        if (!is_string($text) || $text === '') {
            $text = $key;
        }
        $pairs = [];
        foreach ($params as $name => $value) {
            $pairs['{' . $name . '}'] = (string) $value;
        }

        return strtr($text, $pairs);
    }
}

if (!function_exists('complaints_e')) {
    function complaints_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('complaints_te')) {
    /**
     * Экранированная строка языкового файла.
     *
     * @param array<string, scalar> $params
     */
    function complaints_te(string $key, array $params = []): string
    {
        return complaints_e(complaints_t($key, $params));
    }
}

if (!function_exists('complaints_url')) {
    /**
     * Адрес страницы модуля с префиксом языка: /ru/complaint_supplier/show/5.
     *
     * @param array<string, scalar|null> $query
     */
    function complaints_url(string $path, array $query = []): string
    {
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

        return '/' . complaints_language() . '/' . ltrim($path, '/') . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

if (!function_exists('complaints_text')) {
    /**
     * Текст на языке интерфейса.
     */
    function complaints_text(LocalizedText $text): string
    {
        return complaints_e($text->in(complaints_language()));
    }
}

if (!function_exists('complaints_moment')) {
    /**
     * Дата и время в часовом поясе модуля: 16.10.2026 11:00.
     */
    function complaints_moment(?DateTimeInterface $moment): string
    {
        if ($moment === null) {
            return '—';
        }

        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone(complaints_config('time_zone')))
            ->format('d.m.Y H:i');
    }
}

if (!function_exists('complaints_config')) {
    /**
     * Значение из application/config/complaints.php.
     */
    function complaints_config(string $key): mixed
    {
        $config = get_instance()->config->item('complaints');

        return is_array($config) ? ($config[$key] ?? null) : null;
    }
}

if (!function_exists('complaints_label')) {
    /**
     * Метка статуса Bootstrap: <span class="label label-info">Подана</span>.
     */
    function complaints_label(string $group, ?BackedEnum $status): string
    {
        if ($status === null) {
            return '—';
        }
        $classes = [
            'draft' => 'default', 'awaiting_signature' => 'warning', 'signed' => 'info', 'submitted' => 'primary',
            'withdrawn' => 'default', 'in_review' => 'warning', 'reviewed' => 'success',
            'on_coordination' => 'info', 'on_approval' => 'info', 'recalled' => 'danger', 'approved' => 'success',
            'not_required' => 'default', 'in_progress' => 'warning', 'executed' => 'success',
            'pending' => 'warning', 'started' => 'info',
            'satisfied' => 'success', 'rejected' => 'danger',
            'cancel_purchase' => 'danger', 'revise_results' => 'warning', 'no_action' => 'default',
        ];
        $value = (string) $status->value;

        return '<span class="label label-' . ($classes[$value] ?? 'default') . '">'
            . complaints_te('complaints_' . $group . '_' . $value) . '</span>';
    }
}

if (!function_exists('complaints_csrf_name')) {
    function complaints_csrf_name(): string
    {
        return get_instance()->security->get_csrf_token_name();
    }
}

if (!function_exists('complaints_csrf_hash')) {
    function complaints_csrf_hash(): string
    {
        return get_instance()->security->get_csrf_hash();
    }
}

if (!function_exists('complaints_size')) {
    /**
     * Размер файла: 1,5 МБ.
     */
    function complaints_size(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' ' . complaints_t('complaints_unit_mb');
        }

        return number_format(max(1, (int) ceil($bytes / 1024)), 0, ',', ' ') . ' ' . complaints_t('complaints_unit_kb');
    }
}
