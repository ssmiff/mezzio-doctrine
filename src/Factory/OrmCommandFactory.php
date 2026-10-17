<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\ORM\Tools\Console\Command\AbstractEntityManagerCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Console\ConnectionOption;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

use function is_subclass_of;
use function sprintf;

final readonly class OrmCommandFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null,
    ): AbstractEntityManagerCommand {
        if (! is_subclass_of($requestedName, AbstractEntityManagerCommand::class)) {
            throw new InvalidArgumentException(sprintf('%s is not a Doctrine ORM command.', $requestedName));
        }

        $command = new $requestedName(Services::get($container, EntityManagerProvider::class));
        ConnectionOption::addTo($command, Services::get($container, Registry::class));

        return $command;
    }
}
