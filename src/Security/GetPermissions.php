<?php

namespace LaminasAttributeController\Security;

interface GetPermissions
{
    /**
     * Get the permissions of the current user.
     *
     * @return array<string> An array of permission strings.
     */
    public function getPermissions(): array;
}
