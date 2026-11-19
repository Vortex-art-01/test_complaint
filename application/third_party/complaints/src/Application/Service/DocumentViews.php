<?php

declare(strict_types=1);

namespace Complaints\Application\Service;

use Complaints\Application\Port\FileStorage;
use Complaints\Application\Port\OrganizationDirectory;
use Complaints\Application\Repository\AnswerRepository;
use Complaints\Application\Repository\AttachmentRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\View\AnswerView;
use Complaints\Application\View\AttachmentView;
use Complaints\Application\View\DocumentView;
use Complaints\Application\View\SignatureView;
use Complaints\Domain\Document\AttachmentOwner;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;

/**
 * Сборка представлений документов, подписей, вложений и ответов для экранов.
 */
final class DocumentViews
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly SignatureService $signatures,
        private readonly AttachmentRepository $attachments,
        private readonly AnswerRepository $answers,
        private readonly FileStorage $files,
        private readonly OrganizationDirectory $directory,
    ) {
    }

    public function current(DocumentType $type, int $ownerId): ?DocumentView
    {
        $document = $this->documents->current($type, $ownerId);

        return $document === null ? null : new DocumentView($document, $this->files->describe($document->fileId));
    }

    /**
     * @return list<SignatureView>
     */
    public function signatures(Document $document): array
    {
        return array_map(
            fn ($signature): SignatureView => new SignatureView(
                $signature,
                $this->directory->user($signature->signerUserId)->fullName,
            ),
            $this->signatures->signatures($document),
        );
    }

    /**
     * @return list<AttachmentView>
     */
    public function attachments(AttachmentOwner $owner, int $ownerId): array
    {
        return array_map(
            fn ($attachment): AttachmentView => new AttachmentView($attachment, $this->files->describe($attachment->fileId)),
            $this->attachments->of($owner, $ownerId),
        );
    }

    public function answer(?int $answerId): ?AnswerView
    {
        if ($answerId === null) {
            return null;
        }

        return new AnswerView(
            $this->answers->get($answerId),
            $this->current(DocumentType::Answer, $answerId),
            $this->attachments(AttachmentOwner::Answer, $answerId),
        );
    }
}
