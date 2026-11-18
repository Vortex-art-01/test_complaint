<?php

declare(strict_types=1);

namespace Complaints\Domain\Document;

/**
 * Чему принадлежит вложение.
 */
enum AttachmentOwner: string
{
    case AppComplaint = 'app_complaint';
    case Answer = 'answer';
}
