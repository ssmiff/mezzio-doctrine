<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use InvalidArgumentException;
use Override;
use Ssmiff\MezzioDoctrine\Dbal\SearchPathMiddleware;
use Ssmiff\MezzioDoctrine\ParamsProvider;
use Ssmiff\MezzioDoctrine\Test\Fixture\RecordingDriver;

final class SearchPathTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        RecordingDriver::reset();
    }

    public function testMiddlewareSetsTheSearchPathOnConnect(): void
    {
        $connection = $this->createMock(DriverConnection::class);
        $connection->expects(self::once())->method('exec')->with('SET search_path TO "tenant_a", "$user", "public"');

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($connection);

        $wrapped = new SearchPathMiddleware(['tenant_a', '$user', 'public'])->wrap($driver);

        self::assertSame($connection, $wrapped->connect([]));
    }

    public function testSchemaNamesAreQuotedAsIdentifiers(): void
    {
        self::assertSame(
            'SET search_path TO "we""ird", "a, b; DROP TABLE x"',
            new SearchPathMiddleware(['we"ird', 'a, b; DROP TABLE x'])->sql(),
        );
    }

    public function testEmptySearchPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SearchPathMiddleware([]);
    }

    public function testEmptySchemaNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SearchPathMiddleware(['public', '']);
    }

    public function testConnectionConfigSetsTheSearchPath(): void
    {
        $this->registry([
            'connections' => [
                'main' => [
                    'driver'      => 'pdo_pgsql',
                    'driverClass' => RecordingDriver::class,
                    'search_path' => ['tenant_a', 'public'],
                ],
            ],
        ])->connection()->getNativeConnection();

        self::assertSame(['SET search_path TO "tenant_a", "public"'], RecordingDriver::$executed);
        self::assertArrayNotHasKey('search_path', RecordingDriver::$params);
    }

    public function testASingleSchemaCanBeGivenAsAString(): void
    {
        $this->registry([
            'connections' => [
                'main' => [
                    'driver'      => 'pdo_pgsql',
                    'driverClass' => RecordingDriver::class,
                    'search_path' => 'tenant_a',
                ],
            ],
        ])->connection()->getNativeConnection();

        self::assertSame(['SET search_path TO "tenant_a"'], RecordingDriver::$executed);
    }

    public function testNoSearchPathMeansNoStatement(): void
    {
        $this->registry([
            'connections' => ['main' => ['driver' => 'pdo_pgsql', 'driverClass' => RecordingDriver::class]],
        ])->connection()->getNativeConnection();

        self::assertSame([], RecordingDriver::$executed);
    }

    public function testParamsProviderCanSupplyTheSearchPath(): void
    {
        $container = $this->container([
            'connections' => ['main' => ['params_provider' => 'tenant.params', 'search_path' => 'from_config']],
        ]);
        $container->setService('tenant.params', new class implements ParamsProvider {
            #[Override]
            public function params(): array
            {
                return [
                    'driver'      => 'pdo_pgsql',
                    'driverClass' => RecordingDriver::class,
                    'search_path' => ['tenant_b'],
                ];
            }
        });

        $this->registryFrom($container)->connection()->getNativeConnection();

        self::assertSame(['SET search_path TO "tenant_b"'], RecordingDriver::$executed);
    }

    public function testConfigSearchPathAppliesAlongsideAParamsProvider(): void
    {
        $container = $this->container([
            'connections' => ['main' => ['params_provider' => 'tenant.params', 'search_path' => 'from_config']],
        ]);
        $container->setService('tenant.params', new class implements ParamsProvider {
            #[Override]
            public function params(): array
            {
                return ['driver' => 'pdo_pgsql', 'driverClass' => RecordingDriver::class];
            }
        });

        $this->registryFrom($container)->connection()->getNativeConnection();

        self::assertSame(['SET search_path TO "from_config"'], RecordingDriver::$executed);
    }

    public function testSearchPathOnANonPostgresDriverIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('search_path is only supported on PostgreSQL, not on driver "pdo_sqlite".');

        $this->registry([
            'connections' => ['main' => ['search_path' => 'tenant_a']],
        ])->connection();
    }

    public function testInvalidSearchPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('search_path schema names must be strings, got int.');

        $this->registry([
            'connections' => [
                'main' => ['driver' => 'pdo_pgsql', 'driverClass' => RecordingDriver::class, 'search_path' => [1]],
            ],
        ])->connection();
    }
}
