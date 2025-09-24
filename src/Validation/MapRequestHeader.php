<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use Attribute;
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class MapRequestHeader
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
