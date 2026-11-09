<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use InvalidArgumentException;
use Override;
use SensitiveParameter;

use function array_map;
use function implode;
use function str_replace;

/**
 * Sets the PostgreSQL search_path on every new physical connection.
 */
final readonly class SearchPathMiddleware implements Middleware
{
    private string $sql;

    /** @param list<string> $schemas */
    public function __construct(array $schemas)
    {
        if ($schemas === []) {
            throw new InvalidArgumentException('search_path needs at least one schema.');
        }

        foreach ($schemas as $schema) {
            if ($schema === '') {
                throw new InvalidArgumentException('search_path schema names cannot be empty.');
            }
        }

        $this->sql = 'SET search_path TO ' . implode(', ', array_map(self::quote(...), $schemas));
    }

    public function sql(): string
    {
        return $this->sql;
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->sql) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $sql)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $connection->exec($this->sql);

                return $connection;
            }
        };
    }

    private static function quote(string $schema): string
    {
        return '"' . str_replace('"', '""', $schema) . '"';
    }
}
