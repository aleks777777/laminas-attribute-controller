<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use function array_map;
use function array_shift;
use function explode;
use function implode;
use function str_contains;
use function strtolower;
use function trim;
use function uasort;

final class AcceptHeader
{
    /** @var array<string, AcceptHeaderItem> */
    private array $items;

    /**
     * @param array<string, AcceptHeaderItem> $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function fromString(string $header): self
    {
        $parts = array_map('trim', explode(',', $header));
        $items = [];
        $index = 0;

        foreach ($parts as $part) {
            if ('' === $part) {
                continue;
            }

            $segments = explode(';', $part);
            $value = trim((string) array_shift($segments));
            if ('' === $value) {
                continue;
            }

            $quality = 1.0;
            $attributes = [];

            foreach ($segments as $segment) {
                $segment = trim($segment);
                if ('' === $segment) {
                    continue;
                }

                if (str_contains($segment, '=')) {
                    [$key, $rawValue] = explode('=', $segment, 2);
                    $key = trim($key);
                    $rawValue = trim($rawValue);
                    $valuePart = trim($rawValue, "'\"");

                    if ('q' === strtolower($key)) {
                        $quality = (float) $valuePart;
                        continue;
                    }

                    $attributes[$key] = $valuePart;
                    continue;
                }

                $attributes[$segment] = null;
            }

            $items[$value] = new AcceptHeaderItem($value, $quality, $attributes, $index++, $part);
        }

        uasort($items, static function (AcceptHeaderItem $a, AcceptHeaderItem $b): int {
            return $b->getQuality() <=> $a->getQuality() ?: $a->getIndex() <=> $b->getIndex();
        });

        return new self($items);
    }

    /**
     * @return array<string, AcceptHeaderItem>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function __toString(): string
    {
        return implode(',', array_map(
            static fn (AcceptHeaderItem $item): string => (string) $item,
            $this->items,
        ));
    }
}
