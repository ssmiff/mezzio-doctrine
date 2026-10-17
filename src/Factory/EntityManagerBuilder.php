<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Factory;

use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioDoctrine\Support\Caches;
use Ssmiff\MezzioDoctrine\Support\Paths;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\Support\Settings;

use function array_map;
use function in_array;

use const CASE_LOWER;

final readonly class EntityManagerBuilder
{
    public function __construct(private ContainerInterface $container, private Settings $config)
    {
    }

    public function build(string $name, Settings $settings, Connection $connection): EntityManagerInterface
    {
        $base    = Paths::base($this->config);
        $devMode = $settings->bool('dev_mode', $this->config->bool('dev_mode'));
        $cache   = $this->cache($name, $settings, $devMode);

        $namingStrategy = $settings->string('naming_strategy');

        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl($this->mappingDriver($settings->child('mapping'), $base));
        $configuration->setMetadataCache($cache);
        $configuration->setQueryCache($cache);
        $configuration->setResultCache($cache);
        $configuration->setNamingStrategy(
            $namingStrategy === null
                ? new UnderscoreNamingStrategy(CASE_LOWER)
                : Services::get($this->container, NamingStrategy::class, $namingStrategy),
        );
        $configuration->enableNativeLazyObjects(true);

        $eventManager = new EventManager();
        foreach ($settings->strings('event_subscribers') as $id) {
            $eventManager->addEventSubscriber(Services::get($this->container, EventSubscriber::class, $id));
        }

        return new EntityManager($connection, $configuration, $eventManager);
    }

    private function mappingDriver(Settings $mapping, string $base): MappingDriver
    {
        $driver = $mapping->string('driver') ?? 'attributes';
        $paths  = array_map(
            static fn (string $path): string => Paths::resolve($path, $base),
            $mapping->strings('paths'),
        );

        if ($paths === [] && in_array($driver, ['attributes', 'xml'], true)) {
            return new MappingDriverChain();
        }

        return match ($driver) {
            'attributes' => new AttributeDriver($paths),
            'xml' => new XmlDriver($paths),
            default => Services::get($this->container, MappingDriver::class, $driver),
        };
    }

    private function cache(string $name, Settings $settings, bool $devMode): CacheItemPoolInterface
    {
        $service = $settings->string('cache');
        if ($service !== null) {
            return Services::get($this->container, CacheItemPoolInterface::class, $service);
        }

        return Caches::pool('orm_' . $name, $this->config, $devMode);
    }
}
