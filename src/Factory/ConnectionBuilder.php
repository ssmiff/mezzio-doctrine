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
use Ssmiff\MezzioDoctrine\Support\Paths;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_diff_key;
use function array_map;
use function in_array;
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

        $options = array_diff_key($params->toArray(), ['middlewares' => true, 'logger' => true]);

        $path = $params->string('path');
        if ($path !== null && in_array($params->string('driver'), ['pdo_sqlite', 'sqlite3'], true)) {
            $options['path'] = Paths::resolve($path, Paths::base($this->config));
        }

        $configuration = new Configuration();
        $configuration->setMiddlewares($middlewares);

        return DriverManager::getConnection($options, $configuration);
    }

    private function registerTypes(): void
    {
        foreach ($this->config->stringMap('types') as $name => $class) {
            if (! is_subclass_of($class, Type::class)) {
                throw new InvalidArgumentException(sprintf('Type "%s": %s is not a Doctrine DBAL type.', $name, $class));
            }

            if (! Type::hasType($name)) {
                Type::addType($name, $class);
            } elseif (! Type::getType($name) instanceof $class) {
                Type::overrideType($name, $class);
            }
        }
    }
}
