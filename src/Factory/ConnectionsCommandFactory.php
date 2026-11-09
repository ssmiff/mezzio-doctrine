<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Console\ConnectionOption;
use Ssmiff\MezzioDoctrine\Console\ConnectionsCommand;
use Ssmiff\MezzioDoctrine\Console\RunSqlCommand;
use Symfony\Component\Console\Command\Command;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;

final readonly class ConnectionsCommandFactory implements FactoryInterface
{
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): Command {
        $registry = Services::get($container, Registry::class);

        if ($requestedName === RunSqlCommand::class) {
            $command = new RunSqlCommand($registry);
            ConnectionOption::addTo($command, $registry);

            return $command;
        }

        return new ConnectionsCommand($registry);
    }
}
