<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Port\Notification;
use Complaints\Application\Port\Notifier;

final class FakeNotifier implements Notifier
{
    /** @var list<array{to: string, id: int, notification: Notification}> */
    public array $sent = [];

    public function notifyUser(int $userId, Notification $notification): void
    {
        $this->sent[] = ['to' => 'user', 'id' => $userId, 'notification' => $notification];
    }

    public function notifyOrganization(int $organizationId, Notification $notification): void
    {
        $this->sent[] = ['to' => 'organization', 'id' => $organizationId, 'notification' => $notification];
    }

    /**
     * @return list<int>
     */
    public function recipientsOf(string $type, string $to = 'user'): array
    {
        $ids = [];
        foreach ($this->sent as $item) {
            if ($item['notification']->type->value === $type && $item['to'] === $to) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }
}
