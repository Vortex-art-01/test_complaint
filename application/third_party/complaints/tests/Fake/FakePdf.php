<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\PdfRenderer;
use Complaints\Domain\Document\DocumentType;

final class FakePdf implements PdfRenderer
{
    /** @var list<array{template: DocumentType, variables: array<string, mixed>}> */
    public array $rendered = [];

    public function render(DocumentType $template, array $variables): string
    {
        $this->rendered[] = ['template' => $template, 'variables' => $variables];

        return '%PDF-' . $template->value . ' ' . json_encode($variables, JSON_UNESCAPED_UNICODE);
    }
}
