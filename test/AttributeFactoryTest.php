<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Ssmiff\MezzioContainerHelpers\Factory\AttributeFactory;
use Ssmiff\MezzioContainerHelpers\Resolver\ConstructorResolver;
use Ssmiff\MezzioDoctrine\ServiceId;
use Ssmiff\MezzioDoctrine\Test\Fixture\NeedsDatabases;

final class AttributeFactoryTest extends TestCase
{
    public function testAttributesChooseTheDatabaseOfEachParameter(): void
    {
        $container = $this->container();
        $registry  = $this->registryFrom($container);

        $object = new AttributeFactory()($container, NeedsDatabases::class);

        self::assertInstanceOf(NeedsDatabases::class, $object);
        self::assertSame($registry->entityManager('main'), $object->default);
        self::assertSame($registry->entityManager('reporting'), $object->reportingManager);
        self::assertSame($registry->connection('my-db'), $object->myDbConnection);
    }

    public function testPlanListsTheNamedServiceOfEachParameter(): void
    {
        $arguments = new ConstructorResolver()->plan(NeedsDatabases::class)->arguments($this->container());

        self::assertSame(['default', 'reportingManager', 'myDbConnection'], array_keys($arguments));
        self::assertInstanceOf(EntityManagerInterface::class, $arguments['reportingManager']);
        self::assertInstanceOf(Connection::class, $arguments['myDbConnection']);
        self::assertSame(ServiceId::connection('my-db'), 'doctrine.connection.my-db');
    }
}
