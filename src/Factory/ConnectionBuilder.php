<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\Dbal\SearchPathMiddleware;
use Ssmiff\MezzioDoctrine\Support\Paths;
use Ssmiff\MezzioDoctrine\ParamsProvider;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_diff_key;
use function array_filter;
use function array_find;
use function array_is_list;
use function array_map;
use function array_values;
use function count;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;

final readonly class ConnectionBuilder
{
    public function __construct(private ContainerInterface $container, private Settings $config) {}

    public function build(Settings $params): Connection
    {
        $this->registerTypes();

        $middlewares = array_map(
            fn(string $id): Middleware => Services::get($this->container, Middleware::class, $id),
            $params->strings('middlewares'),
        );

        $logger = $params->string('logger');
        if ($logger !== null) {
            $middlewares[] = new LoggingMiddleware(Services::get($this->container, LoggerInterface::class, $logger));
        }

        $provider = $params->string('params_provider');
        $options = $provider === null
            ? array_diff_key($params->toArray(), ['middlewares' => true, 'logger' => true])
            : Services::get($this->container, ParamsProvider::class, $provider)->params();

        $path = new Settings($options)->string('path');
        if ($path !== null && in_array($options['driver'] ?? null, ['pdo_sqlite', 'sqlite3'], true)) {
            $options['path'] = Paths::resolve($path, Paths::base($this->config));
        }

        $searchPath = self::searchPath(
            $options['search_path'] ?? $params->toArray()['search_path'] ?? null,
            $options['driver'] ?? null,
        );
        if ($searchPath !== null) {
            $middlewares[] = $searchPath;
        }
        unset($options['search_path']);

        $configuration = new Configuration();
        $configuration->setMiddlewares($middlewares);

        return DriverManager::getConnection($options, $configuration);
    }

    private static function searchPath(mixed $value, mixed $driver): ?SearchPathMiddleware
    {
        if ($value === null) {
            return null;
        }

        if (is_string($driver) && ! in_array($driver, ['pdo_pgsql', 'pgsql'], true)) {
            throw new InvalidArgumentException(
                sprintf('search_path is only supported on PostgreSQL, not on driver "%s".', $driver),
            );
        }

        if (is_string($value)) {
            return new SearchPathMiddleware([$value]);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf(
                'search_path must be a schema name or a list of schema names, got %s.',
                get_debug_type($value),
            ));
        }

        $names = array_values(array_filter($value, 'is_string'));
        if (count($names) !== count($value)) {
            throw new InvalidArgumentException(sprintf(
                'search_path schema names must be strings, got %s.',
                get_debug_type(array_find($value, static fn(mixed $schema): bool => ! is_string($schema))),
            ));
        }

        return new SearchPathMiddleware($names);
    }

    private function registerTypes(): void
    {
        foreach ($this->config->stringMap('types') as $name => $class) {
            if (! is_subclass_of($class, Type::class)) {
                throw new InvalidArgumentException(
                    sprintf('Type "%s": %s is not a Doctrine DBAL type.', $name, $class),
                );
            }

            if (! Type::hasType($name)) {
                Type::addType($name, $class);
            } elseif (! Type::getType($name) instanceof $class) {
                Type::overrideType($name, $class);
            }
        }
    }
}
