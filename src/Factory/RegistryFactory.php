<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioDoctrine\Support\Settings;

final readonly class RegistryFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null): Registry
    {
        return new Registry(Settings::fromContainer($container), $container);
    }
}
