<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Factory\ConnectionBuilder;
use Ssmiff\MezzioDoctrine\Factory\EntityManagerBuilder;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_combine;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

final class Registry extends AbstractManagerRegistry
{
    private const string CONNECTION = 'connection.';
    private const string MANAGER    = 'manager.';

    /** @var array<string, object> */
    private array $instances = [];

    private readonly ConnectionBuilder $connectionBuilder;
    private readonly EntityManagerBuilder $entityManagerBuilder;

    public function __construct(private readonly Settings $config, ContainerInterface $container)
    {
        $connectionNames = $config->child('connections')->keys();
        if ($connectionNames === []) {
            throw new InvalidArgumentException('No connections configured. Define at least one in doctrine.connections.');
        }

        $managerNames = array_values(array_unique([...$connectionNames, ...$config->child('entity_managers')->keys()]));

        $default = $config->string('default') ?? $connectionNames[0];
        if (! in_array($default, $connectionNames, true)) {
            throw new InvalidArgumentException(sprintf(
                'Default connection "%s" is not defined. Available: %s.',
                $default,
                implode(', ', $connectionNames),
            ));
        }

        $this->connectionBuilder    = new ConnectionBuilder($container, $config);
        $this->entityManagerBuilder = new EntityManagerBuilder($container, $config);

        parent::__construct(
            'Mezzio',
            self::ids(self::CONNECTION, $connectionNames),
            self::ids(self::MANAGER, $managerNames),
            $default,
            $default,
            Proxy::class,
        );
    }

    public function connection(string|null $name = null): Connection
    {
        $connection = $this->getConnection($name);

        return $connection instanceof Connection ? $connection : throw new InvalidArgumentException('Not a DBAL connection.');
    }

    public function entityManager(string|null $name = null): EntityManagerInterface
    {
        $manager = $this->getManager($name);

        return $manager instanceof EntityManagerInterface ? $manager : throw new InvalidArgumentException('Not an ORM entity manager.');
    }

    public function connectionSettings(string $name): Settings
    {
        return $this->config->child('connections')->child($name);
    }

    public function managerConnection(string $manager): string
    {
        return $this->managerSettings($manager)->string('connection') ?? $manager;
    }

    public function versionTable(string $connection): string
    {
        $migrations = $this->config->child('migrations');

        return $migrations->child('connections')->child($connection)->child('table_storage')->string('table_name')
            ?? $migrations->child('defaults')->child('table_storage')->string('table_name')
            ?? 'doctrine_migration_versions';
    }

    #[Override]
    protected function getService(string $name): object
    {
        return $this->instances[$name] ??= $this->create($name);
    }

    #[Override]
    protected function resetService(string $name): void
    {
        unset($this->instances[$name]);
    }

    private function create(string $id): object
    {
        if (str_starts_with($id, self::CONNECTION)) {
            return $this->connectionBuilder->build($this->connectionSettings(substr($id, strlen(self::CONNECTION))));
        }

        $manager = substr($id, strlen(self::MANAGER));

        return $this->entityManagerBuilder->build(
            $manager,
            $this->managerSettings($manager),
            $this->connection($this->managerConnection($manager)),
        );
    }

    private function managerSettings(string $manager): Settings
    {
        return $this->config->child('entity_managers')->child($manager);
    }

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private static function ids(string $prefix, array $names): array
    {
        return array_combine($names, array_map(static fn (string $name): string => $prefix . $name, $names));
    }
}
