<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

final readonly class DefaultConnectionFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Connection
    {
        return Services::get($container, Registry::class)->connection();
    }
}
