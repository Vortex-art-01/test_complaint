<?php

declare(strict_types=1);

namespace Complaints\Application\File\UseCase;

use Complaints\Application\Access\AccessGuard;
use Complaints\Application\Exception\AccessDenied;
use Complaints\Application\Exception\NotFound;
use Complaints\Application\Exception\StatusConflict;
use Complaints\Application\Port\CurrentUserProvider;
use Complaints\Application\Port\FileStorage;
use Complaints\Application\Repository\DocumentRepository;
use Complaints\Application\Service\SignatureService;

/**
 * Байты действующей версии документа в base64 — для подписи ЭЦП в браузере.
 * Выдаются только тому, чья подпись сейчас ожидается по маршруту: свежий
 * сводный документ первым подписывает рассматривающий — при отправке.
 */
final class GetSigningSource
{
    public function __construct(
        private readonly CurrentUserProvider $users,
        private readonly AccessGuard $guard,
        private readonly DocumentRepository $documents,
        private readonly SignatureService $signatures,
        private readonly FileStorage $files,
    ) {
    }

    /**
     * @param int $documentId id документа (id файла)
     *
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
        $this->guard->assertAllowed($this->signatures->isAwaitedFrom($document, $user->userId));

        return base64_encode($this->files->read($document->fileId)->bytes);
    }
}
