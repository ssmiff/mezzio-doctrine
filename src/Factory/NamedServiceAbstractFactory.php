<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioDoctrine\ServiceId;
use Ssmiff\MezzioContainerHelpers\Services;

use function array_key_exists;
use function str_starts_with;
use function strlen;
use function substr;

final readonly class NamedServiceAbstractFactory implements AbstractFactoryInterface
{
    #[Override]
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        if (! str_starts_with($requestedName, 'doctrine.')) {
            return false;
        }

        $registry = Services::get($container, Registry::class);

        return match (true) {
            str_starts_with($requestedName, ServiceId::CONNECTION)
                => array_key_exists(substr($requestedName, strlen(ServiceId::CONNECTION)), $registry->getConnectionNames()),
            str_starts_with($requestedName, ServiceId::ENTITY_MANAGER)
                => array_key_exists(substr($requestedName, strlen(ServiceId::ENTITY_MANAGER)), $registry->getManagerNames()),
            default => false,
        };
    }

    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): object
    {
        $registry = Services::get($container, Registry::class);

        return str_starts_with($requestedName, ServiceId::CONNECTION)
            ? $registry->connection(substr($requestedName, strlen(ServiceId::CONNECTION)))
            : $registry->entityManager(substr($requestedName, strlen(ServiceId::ENTITY_MANAGER)));
    }
}
