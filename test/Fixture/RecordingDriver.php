<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test\Fixture;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as SQLiteDriver;
use Override;
use SensitiveParameter;

/**
 * An in-memory SQLite driver that records the parameters it was given and every statement passed to exec().
 */
final class RecordingDriver extends AbstractDriverMiddleware
{
    /** @var array<string, mixed> */
    public static array $params = [];

    /** @var list<string> */
    public static array $executed = [];

    public function __construct()
    {
        parent::__construct(new SQLiteDriver());
    }

    public static function reset(): void
    {
        self::$params   = [];
        self::$executed = [];
    }

    #[Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        self::$params = $params;

        return new class (parent::connect(['memory' => true])) extends AbstractConnectionMiddleware {
            #[Override]
            public function exec(string $sql): int|string
            {
                RecordingDriver::$executed[] = $sql;

                return 0;
            }
        };
    }
}
