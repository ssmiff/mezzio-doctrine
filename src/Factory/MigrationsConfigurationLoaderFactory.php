<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\Support\Settings;

final readonly class MigrationsConfigurationLoaderFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MigrationsConfigurationLoader
    {
        return new MigrationsConfigurationLoader(
            Settings::fromContainer($container),
            Services::get($container, Registry::class),
        );
    }
}
