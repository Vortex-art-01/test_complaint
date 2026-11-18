<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Pdf;

use Complaints\Infrastructure\Project\ProjectServiceMissing;

/**
 * Генератор PDF проекта — тот же, которым формируются протоколы.
 */
final class ProjectHtmlToPdf implements HtmlToPdfConverter
{
    public function convert(string $html): string
    {
        // ПРОЕКТ: заменить вызовом существующего сервиса.
        // Преобразовать HTML (UTF-8, A4, книжная) в PDF генератором проекта и вернуть
        // байты PDF. Шрифт должен содержать казахские буквы (ә, ғ, қ, ң, ө, ұ, ү, һ, і):
        // в шаблонах указан DejaVu Sans. Если в проекте установлен mPDF 7+,
        // вместо этого класса можно указать pdf_converter = 'mpdf' в конфиге.
        throw ProjectServiceMissing::in(__METHOD__);
    }
}
