<?php

declare(strict_types=1);

namespace Complaints\Infrastructure\Pdf;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Application\Port\PdfRenderer;
use Complaints\Domain\Document\DocumentType;
use RuntimeException;
use Throwable;

/**
 * PDF по PHP-шаблонам модуля (templates/pdf/<тип документа>.php):
 * шаблон строит HTML, генератор проекта превращает его в PDF.
 */
final class TemplatePdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly HtmlToPdfConverter $converter,
        private readonly string $templatesDirectory,
    ) {
    }

    public function render(DocumentType $template, array $variables): string
    {
        $html = $this->html($template, $variables);
        try {
            return $this->converter->convert($html);
        } catch (Throwable $error) {
            throw new IntegrationFailure('complaints_error_pdf', $error);
        }
    }

    /**
     * HTML документа — для PDF и для проверки шаблонов в тестах.
     *
     * @param array<string, mixed> $variables
     */
    public function html(DocumentType $template, array $variables): string
    {
        $labels = require $this->templatesDirectory . '/labels.php';
        $style = file_get_contents($this->templatesDirectory . '/document.css');
        if (!is_array($labels) || $style === false) {
            throw new RuntimeException('Не найдены labels.php или document.css в ' . $this->templatesDirectory);
        }

        $body = self::include($this->templatesDirectory . '/' . $template->value . '.php', new DocumentTemplate($labels), $variables);

        return '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><style>' . $style . '</style></head><body>'
            . $body . '</body></html>';
    }

    /**
     * @param array<string, mixed> $v
     */
    private static function include(string $file, DocumentTemplate $doc, array $v): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Нет шаблона ' . $file);
        }

        ob_start();
        try {
            (static function (string $file, DocumentTemplate $doc, array $v): void {
                require $file;
            })($file, $doc, $v);

            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
