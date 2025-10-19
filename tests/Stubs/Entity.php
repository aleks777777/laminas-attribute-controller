<?php

declare(strict_types=1);

namespace Tests\Stubs;

final readonly class Entity
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
