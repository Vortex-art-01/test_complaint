<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Pdf;

use Throwable;

/**
 * Преобразование HTML документа модуля в PDF генератором проекта.
 */
interface HtmlToPdfConverter
{
    /**
     * @param string $html полный HTML-документ в UTF-8 (A4, книжная ориентация)
     * @return string байты PDF
     *
     * @throws Throwable генератор не сработал
     */
    public function convert(string $html): string;
}
