<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Console\ConnectionOption;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

use function is_subclass_of;
use function sprintf;

final readonly class MigrationsCommandFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null): DoctrineCommand
    {
        if (! is_subclass_of($requestedName, DoctrineCommand::class)) {
            throw new InvalidArgumentException(sprintf('%s is not a Doctrine Migrations command.', $requestedName));
        }

        $command = new $requestedName(Services::get($container, DependencyFactory::class));
        ConnectionOption::addTo($command, Services::get($container, Registry::class));

        return $command;
    }
}
