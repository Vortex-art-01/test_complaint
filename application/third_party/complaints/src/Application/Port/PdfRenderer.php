<?php

declare(strict_types=1);

namespace Complaints\Application\Port;

use Complaints\Application\Exception\IntegrationFailure;
use Complaints\Domain\Document\DocumentType;

/**
 * Формирование PDF по HTML-шаблону модуля. Документ двуязычный: ru и kz.
 */
interface PdfRenderer
{
    /**
     * @param DocumentType          $template  шаблон документа
     * @param array<string, mixed>  $variables данные шаблона: строки, числа и вложенные массивы
     * @return string байты PDF
     *
     * @throws IntegrationFailure генератор PDF не сработал
     */
    public function render(DocumentType $template, array $variables): string;
}
