<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command as Migrations;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\Command as Orm;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\Persistence\ConnectionRegistry;
use Doctrine\Persistence\ManagerRegistry;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Ssmiff\MezzioDoctrine\Console\ConnectionOptionListener;
use Ssmiff\MezzioDoctrine\Console\ConnectionsCommand;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsConfigurationLoader;

/**
 * @psalm-type Dependencies = array{
 *     aliases: array<string, string>,
 *     factories: array<string, class-string<FactoryInterface>>,
 *     abstract_factories: list<class-string<AbstractFactoryInterface>>,
 * }
 */
final readonly class ConfigProvider
{
    public const string CLI_EVENT_DISPATCHER = 'Laminas\Cli\SymfonyEventDispatcher';

    private const array MIGRATION_COMMANDS = [
        'migrations:migrate'               => Migrations\MigrateCommand::class,
        'migrations:status'                => Migrations\StatusCommand::class,
        'migrations:generate'              => Migrations\GenerateCommand::class,
        'migrations:diff'                  => Migrations\DiffCommand::class,
        'migrations:list'                  => Migrations\ListCommand::class,
        'migrations:execute'               => Migrations\ExecuteCommand::class,
        'migrations:version'               => Migrations\VersionCommand::class,
        'migrations:current'               => Migrations\CurrentCommand::class,
        'migrations:latest'                => Migrations\LatestCommand::class,
        'migrations:up-to-date'            => Migrations\UpToDateCommand::class,
        'migrations:rollup'                => Migrations\RollupCommand::class,
        'migrations:dump-schema'           => Migrations\DumpSchemaCommand::class,
        'migrations:sync-metadata-storage' => Migrations\SyncMetadataCommand::class,
    ];

    private const array ORM_COMMANDS = [
        'orm:info'                 => Orm\InfoCommand::class,
        'orm:validate-schema'      => Orm\ValidateSchemaCommand::class,
        'orm:mapping:describe'     => Orm\MappingDescribeCommand::class,
        'orm:run-dql'              => Orm\RunDqlCommand::class,
        'orm:schema-tool:create'   => Orm\SchemaTool\CreateCommand::class,
        'orm:schema-tool:update'   => Orm\SchemaTool\UpdateCommand::class,
        'orm:schema-tool:drop'     => Orm\SchemaTool\DropCommand::class,
        'orm:clear-cache:metadata' => Orm\ClearCache\MetadataCommand::class,
        'orm:clear-cache:query'    => Orm\ClearCache\QueryCommand::class,
        'orm:clear-cache:result'   => Orm\ClearCache\ResultCommand::class,
    ];

    /**
     * @return array{
     *     dependencies: Dependencies,
     *     'laminas-cli': array{commands: array<string, class-string>},
     *     doctrine: array<string, mixed>,
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->dependencies(),
            'laminas-cli'  => ['commands' => $this->commands()],
            'doctrine'     => [
                'default'         => 'default',
                'dev_mode'        => false,
                'connections'     => [],
                'entity_managers' => [],
                'migrations'      => [],
            ],
        ];
    }

    /** @return Dependencies */
    public function dependencies(): array
    {
        $factories = [
            Registry::class                      => Factory\RegistryFactory::class,
            Connection::class                    => Factory\DefaultConnectionFactory::class,
            EntityManagerInterface::class        => Factory\DefaultEntityManagerFactory::class,
            MigrationsConfigurationLoader::class => Factory\MigrationsConfigurationLoaderFactory::class,
            DependencyFactory::class             => Factory\DependencyFactoryFactory::class,
            EntityManagerProvider::class         => Factory\EntityManagerProviderFactory::class,
            ConnectionOptionListener::class      => Factory\ConnectionOptionListenerFactory::class,
            self::CLI_EVENT_DISPATCHER           => Factory\CliEventDispatcherFactory::class,
            ConnectionsCommand::class            => Factory\ConnectionsCommandFactory::class,
        ];

        foreach (self::MIGRATION_COMMANDS as $class) {
            $factories[$class] = Factory\MigrationsCommandFactory::class;
        }

        foreach (self::ORM_COMMANDS as $class) {
            $factories[$class] = Factory\OrmCommandFactory::class;
        }

        return [
            'aliases'   => [
                ManagerRegistry::class    => Registry::class,
                ConnectionRegistry::class => Registry::class,
            ],
            'factories' => $factories,
            'abstract_factories' => [Factory\NamedServiceAbstractFactory::class],
        ];
    }

    /** @return array<string, class-string> */
    public function commands(): array
    {
        return ['db:connections' => ConnectionsCommand::class, ...self::MIGRATION_COMMANDS, ...self::ORM_COMMANDS];
    }
}
