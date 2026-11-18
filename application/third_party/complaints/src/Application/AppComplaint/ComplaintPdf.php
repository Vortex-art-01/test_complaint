<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Port\PdfRenderer;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Application\Service\DocumentViews;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Application\View\AttachmentView;
use Complaints\Domain\AppComplaint\AppComplaint;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

/**
 * PDF жалобы: данные шаблона, формирование и новая версия документа.
 */
final class ComplaintPdf
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly DocumentIssuer $issuer,
        private readonly DocumentViews $views,
        private readonly DisplayDate $dates,
    ) {
    }

    public function issue(AppComplaint $complaint, Requisites $requisites, int $userId, DateTimeImmutable $now): Document
    {
        $variables = [
            'number' => $complaint->number->value,
            'generated_at' => $this->dates->moment($now),
            'requisites' => $requisites->toArray(),
            'buy_published_at' => $this->dates->moment($requisites->buyPublishedAt),
            'protocol_published_at' => $this->dates->moment($complaint->protocolPublishedAt),
            'appeal_period' => $this->dates->period($complaint->appealPeriod),
            'contact_phone' => (string) $complaint->contactPhone,
            'description' => (string) $complaint->description,
            'attachments' => array_map(
                static fn (AttachmentView $view): string => $view->file->fileName,
                $this->views->attachments(AttachmentOwner::AppComplaint, $complaint->id),
            ),
        ];

        return $this->issuer->issue(
            DocumentType::AppComplaint,
            $complaint->id,
            $this->renderer->render(DocumentType::AppComplaint, $variables),
            'Жалоба_' . $complaint->number->value . '.pdf',
            $userId,
            $now,
        );
    }
}
