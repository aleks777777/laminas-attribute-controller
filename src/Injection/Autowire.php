<?php

declare(strict_types=1);
namespace LaminasAttributeController\Injection;

use Attribute;

#[Attribute]
class Autowire
{
    public function __construct(public ?string $alias = null)
    {
    }
}
