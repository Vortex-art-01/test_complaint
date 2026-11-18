<?php

declare(strict_types=1);

namespace Complaints\Tests\Fake;

use Complaints\Application\Access\CurrentUser;
use Complaints\Application\Exception\NotAuthenticated;
use Complaints\Application\Port\CurrentUserProvider;

final class FakeCurrentUser implements CurrentUserProvider
{
    public ?CurrentUser $user = null;

    public function currentUser(): CurrentUser
    {
        return $this->user ?? throw new NotAuthenticated();
    }
}
