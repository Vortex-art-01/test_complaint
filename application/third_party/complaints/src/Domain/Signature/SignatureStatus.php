<?php

declare(strict_types=1);

namespace Complaints\Domain\Signature;

/**
 * Статус подписи (test_complaint_signatures.status).
 */
enum SignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Annulled = 'annulled';
}
