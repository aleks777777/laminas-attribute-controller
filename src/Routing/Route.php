<?php

declare(strict_types=1);

namespace LaminasAttributeController\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Route
{
    /**
     * @param string        $path
     * @param string|null   $name
     * @param array<string> $methods
     */
    public function __construct(
        public string $path,
        public ?string $name = null,
        public array $methods = ['GET'],
    ) {
    }
}
