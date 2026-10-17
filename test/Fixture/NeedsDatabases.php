<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Ssmiff\MezzioDoctrine\Attribute\UseConnection;
use Ssmiff\MezzioDoctrine\Attribute\UseEntityManager;

final readonly class NeedsDatabases
{
    public function __construct(
        public EntityManagerInterface $default,
        #[UseEntityManager('reporting')]
        public EntityManagerInterface $reportingManager,
        #[UseConnection('my-db')]
        public Connection $myDbConnection,
    ) {
    }
}
