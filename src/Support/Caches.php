<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Support;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

final readonly class Caches
{
    public static function pool(string $namespace, Settings $config, bool $devMode): CacheItemPoolInterface
    {
        if ($devMode) {
            return new ArrayAdapter();
        }

        $directory = Paths::resolve($config->string('cache_dir') ?? 'data/cache/doctrine', Paths::base($config)) . '/' . $namespace;

        return PhpFilesAdapter::isSupported()
            ? new PhpFilesAdapter('doctrine_' . $namespace, 0, $directory)
            : new FilesystemAdapter('doctrine_' . $namespace, 0, $directory);
    }
}
