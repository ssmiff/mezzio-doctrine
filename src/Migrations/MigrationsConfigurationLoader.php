<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Migrations;

use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\Configuration\Migration\ConfigurationLoader;
use InvalidArgumentException;
use Override;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioDoctrine\Support\Paths;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_keys;
use function implode;
use function is_dir;
use function mkdir;
use function sprintf;

final class MigrationsConfigurationLoader implements ConfigurationLoader
{
    public private(set) string|null $selected = null;

    public function __construct(private readonly Settings $config, private readonly Registry $registry)
    {
    }

    public function select(string $connection): void
    {
        $known = $this->registry->getConnectionNames();
        if (! isset($known[$connection])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown connection "%s". Available connections: %s.',
                $connection,
                implode(', ', array_keys($known)),
            ));
        }

        $this->selected = $connection;
    }

    public function current(): string
    {
        return $this->selected ?? $this->registry->getDefaultConnectionName();
    }

    #[Override]
    public function getConfiguration(): Configuration
    {
        $name       = $this->current();
        $migrations = $this->config->child('migrations');
        $defaults   = $migrations->child('defaults');
        $override   = $migrations->child('connections')->child($name);
        $base       = Paths::base($this->config);

        $settings = [...$defaults->toArray(), ...$override->toArray()];

        $configured = ($override->has('migrations_paths') ? $override : $defaults)->stringMap('migrations_paths');
        $paths      = [];
        foreach ($configured ?: ['Database\\Migrations\\' . Paths::studly($name) => 'database/migrations/' . $name] as $namespace => $path) {
            $paths[$namespace] = Paths::resolve($path, $base);

            if (! is_dir($paths[$namespace])) {
                @mkdir($paths[$namespace], 0775, true);
            }
        }

        $settings['migrations_paths'] = $paths;
        $settings += ['table_storage' => ['table_name' => 'doctrine_migration_versions'], 'em' => $name];

        return new ConfigurationArray($settings)->getConfiguration();
    }
}
