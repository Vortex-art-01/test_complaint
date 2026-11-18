<?php

declare(strict_types=1);

namespace Complaints\Application\View;

use Complaints\Application\Port\FileDescription;
use Complaints\Domain\Document\Attachment;

/**
 * Вложение и сведения о его файле.
 */
final readonly class AttachmentView
{
    public function __construct(
        public Attachment $attachment,
        public FileDescription $file,
    ) {
    }
}
