<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Console\RegistryEntityManagerProvider;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

final readonly class EntityManagerProviderFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RegistryEntityManagerProvider
    {
        return new RegistryEntityManagerProvider(Services::get($container, Registry::class));
    }
}
