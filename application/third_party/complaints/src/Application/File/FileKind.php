<?php

declare(strict_types=1);

namespace Complaints\Application\File;

/**
 * Что скачивается: версия документа, вложение или файл подписи.
 */
enum FileKind: string
{
    case Document = 'document';
    case Attachment = 'attachment';
}
