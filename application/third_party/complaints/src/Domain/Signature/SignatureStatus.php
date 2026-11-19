<?php

declare(strict_types=1);

namespace Complaints\Domain\Signature;

enum SignatureStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
}
