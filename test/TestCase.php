<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use Laminas\ServiceManager\ServiceManager;
use Override;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Ssmiff\MezzioContainerHelpers\ConfigProvider as HelpersConfigProvider;
use Ssmiff\MezzioDoctrine\ConfigProvider;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;
use Symfony\Component\Filesystem\Filesystem;

use function array_replace_recursive;
use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

abstract class TestCase extends PhpUnitTestCase
{
    protected string $base;

    #[Override]
    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/mezzio-doctrine-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->base);
    }

    /** @param array<array-key, mixed> $doctrine */
    protected function container(array $doctrine = []): ServiceManager
    {
        $provider = new ConfigProvider();
        $config   = $provider();

        $config['doctrine'] = array_replace_recursive(
            $config['doctrine'],
            [
                'default'     => 'main',
                'base_path'   => $this->base,
                'connections' => [
                    'main'      => ['driver' => 'pdo_sqlite', 'memory' => true],
                    'reporting' => ['driver' => 'pdo_sqlite', 'memory' => true],
                    'my-db'     => ['driver' => 'pdo_sqlite', 'memory' => true],
                ],
            ],
            $doctrine,
        );

        $dependencies              = $provider->dependencies();
        $dependencies['factories'] = [
            ...$dependencies['factories'],
            ...new HelpersConfigProvider()->dependencies()['factories'],
        ];

        $container = new ServiceManager($dependencies);
        $container->setService('config', $config);

        return $container;
    }

    protected function registryFrom(ServiceManager $container): Registry
    {
        return Services::get($container, Registry::class);
    }

    /** @param array<array-key, mixed> $doctrine */
    protected function registry(array $doctrine = []): Registry
    {
        return Services::get($this->container($doctrine), Registry::class);
    }
}
