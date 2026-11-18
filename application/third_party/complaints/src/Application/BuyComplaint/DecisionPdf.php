<?php

declare(strict_types=1);

namespace Complaints\Application\BuyComplaint;

use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\OrganizationCard;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Port\PdfRenderer;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AppComplaintRepository;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\ProcurementRepository;
use Complaints\Application\Service\DocumentIssuer;
use Complaints\Application\Service\DocumentViews;
use Complaints\Application\Shared\DisplayDate;
use Complaints\Application\Shared\LocalizedText;
use Complaints\Application\View\AttachmentView;
use Complaints\Domain\AppComplaint\AppComplaintLot;
use Complaints\Domain\BuyComplaint\Answer;
use Complaints\Domain\BuyComplaint\BuyComplaint;
use Complaints\Domain\BuyComplaint\BuyComplaintLot;
use Complaints\Domain\BuyComplaint\ReviewMethod;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;
use DateTimeImmutable;

/**
 * PDF решения: ответ на жалобу (жалобы лота) и сводный документ ответов
 * с реестром PDF ответов и их контрольными суммами.
 */
final class DecisionPdf
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly DocumentIssuer $issuer,
        private readonly DocumentViews $views,
        private readonly DocumentRepository $documents,
        private readonly BuyComplaintRepository $decisions,
        private readonly AppComplaintRepository $complaints,
        private readonly AnswerRepository $answers,
        private readonly ProcurementRepository $procurement,
        private readonly OrganizationDirectory $directory,
        private readonly FileStorage $files,
        private readonly DisplayDate $dates,
    ) {
    }

    /**
     * @param list<AppComplaintLot> $complaintLots лоты жалоб, к которым относится ответ
     */
    public function answer(
        BuyComplaint $decision,
        BuyComplaintLot $lot,
        Answer $answer,
        array $complaintLots,
        int $userId,
        DateTimeImmutable $now,
    ): Document {
        $buy = $this->procurement->buy($decision->buyId);
        $buyLot = $this->procurement->buyLot($lot->buyLotId);
        $complaints = array_map(fn (AppComplaintLot $complaintLot): array => $this->complaintRow($complaintLot), $complaintLots);
        $perLot = $decision->reviewMethod === ReviewMethod::PerLot;

        $variables = [
            'per_lot' => $perLot,
            'buy' => ['number' => $buy->number, 'name' => self::text($buy->name)],
            'lot' => ['number' => $buyLot->number, 'name' => self::text($buyLot->name)],
            'complaints' => $complaints,
            'answer_text' => $answer->text,
            'attachments' => array_map(
                static fn (AttachmentView $view): string => $view->file->fileName,
                $this->views->attachments(AttachmentOwner::Answer, $answer->id),
            ),
            'authority' => $this->organization($this->directory->organization($decision->authorityOrgId)),
            'reviewer' => $this->reviewerName($decision),
            'generated_at' => $this->dates->moment($now),
        ];
        $fileName = $perLot
            ? 'Ответ_лот_' . $buyLot->number . '.pdf'
            : 'Ответ_' . ($complaints[0]['number'] ?? (string) $answer->id) . '.pdf';

        return $this->issuer->issue(
            DocumentType::Answer,
            $answer->id,
            $this->renderer->render(DocumentType::Answer, $variables),
            $fileName,
            $userId,
            $now,
        );
    }

    public function consolidated(BuyComplaint $decision, int $userId, DateTimeImmutable $now): Document
    {
        $buy = $this->procurement->buy($decision->buyId);
        $lots = [];
        $registry = [];

        foreach ($this->decisions->lots($decision->id) as $lot) {
            $buyLot = $this->procurement->buyLot($lot->buyLotId);
            $complaintLots = $this->complaints->lotsOfBuyComplaintLot($lot->id);
            $answers = [];
            foreach ($this->groupByAnswer($complaintLots) as $answerId => $numbers) {
                $document = $this->documents->current(DocumentType::Answer, $answerId);
                $file = $document === null ? '' : $this->files->describe($document->fileId)->fileName;
                $answers[] = [
                    'complaints' => $numbers,
                    'text' => $this->answers->get($answerId)->text,
                    'file' => $file,
                    'sha256' => $document?->sha256->value ?? '',
                ];
                $registry[] = ['file' => $file, 'sha256' => $document?->sha256->value ?? ''];
            }

            $lots[] = [
                'number' => $buyLot->number,
                'name' => self::text($buyLot->name),
                'decision_type' => $lot->decisionType?->value,
                'complaints' => array_map(fn (AppComplaintLot $complaintLot): array => $this->complaintRow($complaintLot), $complaintLots),
                'answers' => $answers,
            ];
        }

        $variables = [
            'buy' => ['number' => $buy->number, 'name' => self::text($buy->name)],
            'authority' => $this->organization($this->directory->organization($decision->authorityOrgId)),
            'reviewer' => $this->reviewerName($decision),
            'review_method' => $decision->reviewMethod?->value,
            'review_period' => $this->dates->period($decision->reviewPeriod),
            'lots' => $lots,
            'registry' => $registry,
            'generated_at' => $this->dates->moment($now),
        ];

        return $this->issuer->issue(
            DocumentType::ConsolidatedAnswer,
            $decision->id,
            $this->renderer->render(DocumentType::ConsolidatedAnswer, $variables),
            'Сводный_ответ_закупка_' . $buy->number . '.pdf',
            $userId,
            $now,
        );
    }

    /**
     * @param list<AppComplaintLot> $complaintLots
     * @return array<int, list<string>> id ответа => номера жалоб
     */
    private function groupByAnswer(array $complaintLots): array
    {
        $groups = [];
        foreach ($complaintLots as $complaintLot) {
            if ($complaintLot->answerId !== null) {
                $groups[$complaintLot->answerId][] = $this->complaints->get($complaintLot->complaintId)->number->value;
            }
        }

        return $groups;
    }

    /**
     * @return array{number: string, supplier_bin: string, supplier_name: array{ru: string, kz: string}, verdict: ?string}
     */
    private function complaintRow(AppComplaintLot $complaintLot): array
    {
        $complaint = $this->complaints->get($complaintLot->complaintId);
        $supplier = $this->directory->organization($complaint->supplierOrgId);

        return [
            'number' => $complaint->number->value,
            'supplier_bin' => $supplier->bin,
            'supplier_name' => self::text($supplier->name),
            'verdict' => $complaintLot->verdict?->value,
        ];
    }

    /**
     * @return array{bin: string, name: array{ru: string, kz: string}}
     */
    private function organization(OrganizationCard $organization): array
    {
        return ['bin' => $organization->bin, 'name' => self::text($organization->name)];
    }

    private function reviewerName(BuyComplaint $decision): string
    {
        return $decision->reviewerUserId === null ? '' : $this->directory->user($decision->reviewerUserId)->fullName;
    }

    /**
     * @return array{ru: string, kz: string}
     */
    private static function text(LocalizedText $text): array
    {
        return ['ru' => $text->ru, 'kz' => $text->kz];
    }
}
