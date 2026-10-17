<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Support;

use function getcwd;
use function preg_match;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function ucwords;

final readonly class Paths
{
    public static function base(Settings $config): string
    {
        return rtrim($config->string('base_path') ?? (getcwd() ?: '.'), '/\\');
    }

    public static function resolve(string $path, string $base): string
    {
        $absolute = $path === ''
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || str_starts_with($path, ':')
            || str_starts_with($path, 'file:')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;

        return $absolute ? $path : $base . '/' . $path;
    }

    public static function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $name)));
    }
}
