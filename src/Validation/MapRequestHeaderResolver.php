<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use ArrayIterator;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Header\HeaderInterface;
use Laminas\Http\Request;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;
use LogicException;

use function array_keys;
use function array_map;
use function array_shift;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_contains;
use function strtolower;
use function strtoupper;

final readonly class MapRequestHeaderResolver implements ParameterResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(ResolutionContext $context): ParameterValue
    {
        foreach ($context->getAttributes() as $attribute) {
            if ($attribute->getName() !== MapRequestHeader::class) {
                continue;
            }

            /** @var MapRequestHeader $attributeInstance */
            $attributeInstance = $attribute->newInstance();

            $type = $context->parameter->getType();
            $typeName = $type?->getName();

            if (!\in_array($typeName, ['string', 'array', AcceptHeader::class], true)) {
                throw new LogicException(sprintf(
                    'Could not resolve the argument typed "%s". Valid values types are "array", "string" or "%s".',
                    (string) $typeName,
                    AcceptHeader::class,
                ));
            }

            $headerName = $attributeInstance->name ?? $context->parameter->getName();
            $value = null;

            if ($this->request->getHeaders()->has($headerName)) {
                $header = $this->request->getHeaders($headerName);

                $value = match ($typeName) {
                    'string' => $this->extractHeaderValue($header),
                    'array' => $this->extractHeaderArray($headerName, $header),
                    default => $this->extractAcceptHeader($header),
                };
            }

            if (null === $value && $context->parameter->isDefaultValueAvailable()) {
                $value = $context->parameter->getDefaultValue();
            }

            if (null === $value && 'array' === $typeName) {
                $value = [];
            }

            if (null === $value && ! $context->parameter->allowsNull()) {
                throw new InvalidArgumentException(sprintf('Missing header "%s".', $headerName));
            }

            return ParameterValue::found(MapRequestHeader::class, $value);
        }

        return ParameterValue::notFound();
    }

    private function extractHeaderValue(HeaderInterface|ArrayIterator|false $header): ?string
    {
        if ($header instanceof ArrayIterator) {
            $header->rewind();
            $current = $header->current();
            if ($current instanceof HeaderInterface) {
                return $current->getFieldValue();
            }

            return null;
        }

        if ($header instanceof HeaderInterface) {
            return $header->getFieldValue();
        }

        if (false === $header) {
            return null;
        }

        return (string) $header;
    }

    private function extractHeaderLine(HeaderInterface|ArrayIterator|false $header): ?string
    {
        if ($header instanceof ArrayIterator) {
            $values = [];
            foreach ($header as $item) {
                if ($item instanceof HeaderInterface) {
                    $values[] = $item->getFieldValue();
                }
            }

            if ([] === $values) {
                return null;
            }

            return implode(',', $values);
        }

        if ($header instanceof HeaderInterface) {
            return $header->getFieldValue();
        }

        if (false === $header) {
            return null;
        }

        return (string) $header;
    }

    private function extractHeaderArray(string $name, HeaderInterface|ArrayIterator|false $header): array
    {
        $normalizedName = strtolower($name);
        $line = $this->extractHeaderLine($header);

        return match ($normalizedName) {
            'accept' => $line === null ? [] : array_map('strval', array_keys(AcceptHeader::fromString($line)->all())),
            'accept-charset', 'accept-encoding' => $line === null ? [] : array_map('strval', array_keys(AcceptHeader::fromString($line)->all())),
            'accept-language' => $this->extractAcceptLanguages($line),
            default => $this->extractGenericArray($header),
        };
    }

    private function extractAcceptHeader(HeaderInterface|ArrayIterator|false $header): ?AcceptHeader
    {
        $line = $this->extractHeaderLine($header);

        return null === $line ? null : AcceptHeader::fromString($line);
    }

    private function extractGenericArray(HeaderInterface|ArrayIterator|false $header): array
    {
        if ($header instanceof ArrayIterator) {
            $values = [];
            foreach ($header as $item) {
                if ($item instanceof HeaderInterface) {
                    $values[] = $item->getFieldValue();
                }
            }

            return $values;
        }

        if ($header instanceof HeaderInterface) {
            return [$header->getFieldValue()];
        }

        return [];
    }

    private function extractAcceptLanguages(?string $line): array
    {
        if (null === $line) {
            return [];
        }

        $languages = [];
        foreach (array_keys(AcceptHeader::fromString($line)->all()) as $language) {
            if (str_contains($language, '-')) {
                $parts = explode('-', $language);
                if ('i' === $parts[0]) {
                    if (count($parts) > 1) {
                        $language = $parts[1];
                    }
                } else {
                    $primary = strtolower(array_shift($parts));
                    $language = $primary;
                    foreach ($parts as $part) {
                        $language .= '_'.strtoupper($part);
                    }
                }
            }

            $languages[] = $language;
        }

        return $languages;
    }
}
