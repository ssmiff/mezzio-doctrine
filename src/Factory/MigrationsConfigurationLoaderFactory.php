<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsPathsProvider;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_map;

final readonly class MigrationsConfigurationLoaderFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): MigrationsConfigurationLoader {
        $settings = Settings::fromContainer($container);

        return new MigrationsConfigurationLoader(
            $settings,
            Services::get($container, Registry::class),
            array_map(
                static fn(string $id): MigrationsPathsProvider => Services::get(
                    $container,
                    MigrationsPathsProvider::class,
                    $id,
                ),
                $settings->child('migrations')->strings('paths_providers'),
            ),
        );
    }
}
