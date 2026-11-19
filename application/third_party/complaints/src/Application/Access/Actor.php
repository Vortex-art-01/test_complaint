<?php

declare(strict_types=1);

namespace Complaints\Application\Access;

/**
 * Кто выполнил действие: пользователь и его организация на этот момент
 * (поля *_by_user_id и *_by_subject_id).
 */
final readonly class Actor
{
    public function __construct(
        public int $userId,
        public int $organizationId,
    ) {
    }

    public static function of(CurrentUser $user): self
    {
        return new self($user->userId, $user->organizationId);
    }
}
