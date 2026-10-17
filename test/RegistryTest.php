<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioDoctrine\ServiceId;
use Ssmiff\MezzioContainerHelpers\Services;

use function array_keys;
use function mkdir;

final class RegistryTest extends TestCase
{
    public function testResolvesNamedConnectionsLazilyAndOnce(): void
    {
        $registry = $this->registry();

        $main      = $registry->connection();
        $reporting = $registry->connection('reporting');

        self::assertNotSame($main, $reporting);
        self::assertSame($main, $registry->connection('main'));
        self::assertSame($reporting, $registry->connection('reporting'));

        $main->executeStatement('CREATE TABLE only_in_main (id INTEGER)');
        self::assertTrue($main->createSchemaManager()->tablesExist(['only_in_main']));
        self::assertFalse($reporting->createSchemaManager()->tablesExist(['only_in_main']));
    }

    public function testEveryConnectionHasAnEntityManagerOfTheSameName(): void
    {
        $registry = $this->registry();

        self::assertSame(['main', 'reporting', 'my-db'], array_keys($registry->getManagerNames()));
        self::assertSame($registry->connection('reporting'), $registry->entityManager('reporting')->getConnection());
    }

    public function testServicesForInjection(): void
    {
        $container = $this->container();
        $registry  = Services::get($container, Registry::class);

        self::assertSame($registry, Services::get($container, ManagerRegistry::class));
        self::assertSame($registry->connection(), Services::get($container, Connection::class));
        self::assertSame($registry->entityManager(), Services::get($container, EntityManagerInterface::class));
    }

    public function testNamedConnectionsAndEntityManagersAreContainerServices(): void
    {
        $container = $this->container();
        $registry  = Services::get($container, Registry::class);

        self::assertSame(
            $registry->connection('reporting'),
            Services::get($container, Connection::class, ServiceId::connection('reporting')),
        );
        self::assertSame(
            $registry->entityManager('reporting'),
            Services::get($container, EntityManagerInterface::class, ServiceId::entityManager('reporting')),
        );
        self::assertTrue($container->has(ServiceId::entityManager('my-db')));
        self::assertFalse($container->has(ServiceId::entityManager('nope')));
        self::assertFalse($container->has(ServiceId::connection('nope')));
    }

    public function testResetRebuildsTheConnectionAndEntityManager(): void
    {
        $registry = $this->registry();
        $before   = $registry->connection('reporting');
        $manager  = $registry->entityManager('reporting');

        $registry->reset('reporting');

        self::assertNotSame($before, $registry->connection('reporting'));
        self::assertNotSame($manager, $registry->entityManager('reporting'));
        self::assertSame($registry->connection('reporting'), $registry->entityManager('reporting')->getConnection());
    }

    public function testParamsProviderSuppliesConnectionParamsAtRuntime(): void
    {
        $container = $this->container([
            'connections' => ['dynamic' => ['params_provider' => Fixture\FileProvider::class]],
        ]);
        $container->setFactory(Fixture\FileProvider::class, InvokableFactory::class);
        $registry = Services::get($container, Registry::class);

        mkdir($this->base, 0775, true);
        Fixture\FileProvider::$path = $this->base . '/a.sqlite';
        $registry->connection('dynamic')->executeStatement('CREATE TABLE a (id INTEGER)');

        $registry->reset('dynamic');
        Fixture\FileProvider::$path = $this->base . '/b.sqlite';

        self::assertFalse($registry->connection('dynamic')->createSchemaManager()->tablesExist(['a']));
    }

    public function testDefaultCanBeAnyConnection(): void
    {
        $registry = $this->registry(['default' => 'reporting']);

        self::assertSame($registry->connection('reporting'), $registry->connection());
    }

    public function testUnknownConnectionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->connection('nope');
    }

    public function testUnknownDefaultIsRejected(): void
    {
        $this->expectExceptionMessage('Default connection "nope" is not defined. Available: main, reporting, my-db.');

        $this->registry(['default' => 'nope']);
    }

    public function testEntityManagerCanBeResetAfterItCloses(): void
    {
        $registry = $this->registry();
        $first    = $registry->entityManager();
        $first->close();

        self::assertNotSame($first, $registry->resetManager());
    }
}
