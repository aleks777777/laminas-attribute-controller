<?php

declare(strict_types=1);

namespace Tests\Stubs;

use LaminasAttributeController\Security\GetCurrentUser;

final class TestGetCurrentUser implements GetCurrentUser
{
    private ?User $user = null;

    public function auth(User $user): void
    {
        $this->user = $user;
    }

    public function getCurrentUser(): ?User
    {
        return $this->user;
    }
}
