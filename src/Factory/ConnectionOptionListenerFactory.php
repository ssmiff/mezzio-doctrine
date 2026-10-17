<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Console\ConnectionOptionListener;
use Ssmiff\MezzioDoctrine\Console\RegistryEntityManagerProvider;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

final readonly class ConnectionOptionListenerFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ConnectionOptionListener
    {
        return new ConnectionOptionListener(
            Services::get($container, Registry::class),
            Services::get($container, MigrationsConfigurationLoader::class),
            Services::get($container, RegistryEntityManagerProvider::class, EntityManagerProvider::class),
        );
    }
}
