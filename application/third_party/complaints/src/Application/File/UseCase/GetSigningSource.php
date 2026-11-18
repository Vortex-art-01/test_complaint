<?php

declare(strict_types=1);

namespace Complaints\Application\File\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\BuyComplaintRepository;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Repository\SignatureRepository;
use Complaints\Application\Service\SignatureService;
use Complaints\Domain\Document\Document;
use Complaints\Domain\Document\DocumentType;

/**
 * Байты действующей версии документа в base64 — для подписи ЭЦП в браузере.
 * Выдаются тому, чья подпись сейчас ожидается, а свежий сводный документ
 * (ещё без маршрута подписания) — рассматривающему: он подписывает его
 * при отправке на согласование, и маршрут создаётся вместе с его подписью.
 */
final class GetSigningSource
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DocumentRepository $documents,
        private readonly SignatureService $signatures,
        private readonly SignatureRepository $signatureRows,
        private readonly BuyComplaintRepository $decisions,
        private readonly FileStorage $files,
    ) {
    }

    /**
     * @throws NotFound
     * @throws AccessDenied
     * @throws StatusConflict версия больше не действует
     */
    public function execute(int $documentId): string
    {
        $user = $this->users->currentUser();
        $document = $this->documents->get($documentId);
        if (!$document->isCurrent) {
            throw new StatusConflict();
        }
        $this->guard->assertAllowed(
            $this->signatures->isAwaitedFrom($documentId, $user->userId)
            || $this->isUnsentConsolidatedOfReviewer($user, $document),
        );

        return base64_encode($this->files->read($document->fileId)->bytes);
    }

    private function isUnsentConsolidatedOfReviewer(CurrentUser $user, Document $document): bool
    {
        if ($document->type !== DocumentType::ConsolidatedAnswer || $this->signatureRows->ofDocument($document->id) !== []) {
            return false;
        }
        $decision = $this->decisions->get($document->ownerId);

        return $decision->status->isSendable() && $this->guard->canReview($user, $decision);
    }
}
