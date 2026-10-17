<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\Migrations\Configuration\EntityManager\ManagerRegistryEntityManager;
use Doctrine\Migrations\DependencyFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

final readonly class DependencyFactoryFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DependencyFactory
    {
        $registry = Services::get($container, Registry::class);

        return DependencyFactory::fromEntityManager(
            Services::get($container, MigrationsConfigurationLoader::class),
            ManagerRegistryEntityManager::withSimpleDefault($registry, $registry->getDefaultManagerName()),
        );
    }
}
