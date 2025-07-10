<?php

declare(strict_types=1);

namespace LaminasAttributeController\Security;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class PermissionGuard
{
    public function __construct(public string $permission)
    {
    }
}
