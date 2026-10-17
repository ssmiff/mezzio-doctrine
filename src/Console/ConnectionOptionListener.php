<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use InvalidArgumentException;
use RuntimeException;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioDoctrine\Support\InputOptions;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function implode;
use function sprintf;

final readonly class ConnectionOptionListener
{
    public function __construct(
        private Registry $registry,
        private MigrationsConfigurationLoader $migrations,
        private RegistryEntityManagerProvider $entityManagers,
    ) {}

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command === null || ! $command->getDefinition()->hasOption(ConnectionOption::NAME)) {
            return;
        }

        $input = $event->getInput();
        $name  = $command instanceof DoctrineCommand
            ? $this->selectForMigrations($command, $input)
            : $this->selectForOrm($input);

        $event->getOutput()->writeln(sprintf('<info>Using connection "%s"</info>', $name), OutputInterface::VERBOSITY_VERBOSE);
    }

    private function selectForMigrations(DoctrineCommand $command, InputInterface $input): string
    {
        $name = InputOptions::string($input, ConnectionOption::NAME)
            ?? InputOptions::string($input, 'em')
            ?? $this->registry->getDefaultConnectionName();

        $this->migrations->select($name);

        if ($command->getName() === 'migrations:diff' && ! $this->hasMapping($name)) {
            throw new RuntimeException(sprintf(
                'Connection "%1$s" has no mapped entities, so there is nothing to diff against. '
                . 'Write the migration yourself with "migrations:generate --conn=%1$s", '
                . 'or map entities in doctrine.entity_managers.%1$s.',
                $name,
            ));
        }

        return $name;
    }

    private function selectForOrm(InputInterface $input): string
    {
        $requested = InputOptions::string($input, ConnectionOption::NAME);
        $explicit  = InputOptions::string($input, 'em');

        if ($requested !== null && $explicit !== null && $requested !== $explicit) {
            throw new InvalidArgumentException('Use either --conn or --em, not both with different values.');
        }

        $name = $requested ?? $explicit ?? $this->registry->getDefaultManagerName();

        if (! isset($this->registry->getManagerNames()[$name])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown connection "%s". Available connections: %s.',
                $name,
                implode(', ', array_keys($this->registry->getManagerNames())),
            ));
        }

        $this->entityManagers->select($name);
        $this->ignoreVersionTable($name);

        return $name;
    }

    private function ignoreVersionTable(string $manager): void
    {
        $table = $this->registry->versionTable($this->registry->managerConnection($manager));

        $this->registry->entityManager($manager)->getConnection()->getConfiguration()->setSchemaAssetsFilter(
            static fn(string $asset): bool => $asset !== $table,
        );
    }

    private function hasMapping(string $manager): bool
    {
        $driver = $this->registry->entityManager($manager)->getConfiguration()->getMetadataDriverImpl();

        return ($driver?->getAllClassNames() ?? []) !== [];
    }
}
