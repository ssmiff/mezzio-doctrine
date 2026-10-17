<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Support;

use Psr\Container\ContainerInterface;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function strval;

use const ARRAY_FILTER_USE_KEY;

final readonly class Settings
{
    /** @param array<array-key, mixed> $values */
    public function __construct(private array $values = []) {}

    public static function fromContainer(ContainerInterface $container): self
    {
        return new self(self::asArray($container->get('config')))->child('doctrine');
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function child(string $key): self
    {
        return new self(self::asArray($this->values[$key] ?? null));
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(strval(...), array_keys($this->values));
    }

    public function string(string $key): ?string
    {
        return self::asString($this->values[$key] ?? null);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return self::asBool($this->values[$key] ?? null, $default);
    }

    /** @return list<string> */
    public function strings(string $key): array
    {
        return array_values(array_filter($this->child($key)->values, 'is_string'));
    }

    /** @return array<string, string> */
    public function stringMap(string $key): array
    {
        $strings = array_filter($this->child($key)->values, 'is_string');

        return array_combine(array_map(strval(...), array_keys($strings)), $strings);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter($this->values, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** @return array<string, int> */
    public function intMap(string $key): array
    {
        $ints = array_filter($this->child($key)->values, 'is_int');

        return array_combine(array_map(strval(...), array_keys($ints)), $ints);
    }

    /** @return array<array-key, mixed> */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function asBool(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    private static function asString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
