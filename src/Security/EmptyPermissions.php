<?php

namespace LaminasAttributeController\Security;

final class EmptyPermissions implements GetPermissions
{
    public function getPermissions(): array
    {
        return [];
    }

}
