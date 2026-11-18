<?php

declare(strict_types=1);

namespace Complaints\Domain\Signature;

/**
 * Роль подписанта. Жалобу подписывает руководитель, сводный документ —
 * по порядку рассматривающий, согласующий (если назначен) и утверждающий.
 */
enum SignerRole: string
{
    case Head = 'head';
    case Reviewer = 'reviewer';
    case Coordinator = 'coordinator';
    case Approver = 'approver';

    public function signOrder(): int
    {
        return match ($this) {
            self::Head, self::Reviewer => 1,
            self::Coordinator => 2,
            self::Approver => 3,
        };
    }
}
