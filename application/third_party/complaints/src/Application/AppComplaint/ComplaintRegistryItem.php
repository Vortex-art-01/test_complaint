<?php

declare(strict_types=1);

namespace Complaints\Application\AppComplaint;

use Complaints\Application\Repository\ComplaintListRow;

/**
 * Строка реестра жалоб и доступные пользователю действия.
 */
final readonly class ComplaintRegistryItem
{
    /**
     * @param list<ComplaintAction> $actions
     */
    public function __construct(
        public ComplaintListRow $row,
        public array $actions,
        public bool $appealOpen,
    ) {
    }

    public function allows(ComplaintAction $action): bool
    {
        return in_array($action, $this->actions, true);
    }
}
