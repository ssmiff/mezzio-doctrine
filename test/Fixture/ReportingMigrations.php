<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test\Fixture;

use Override;
use Ssmiff\MezzioDoctrine\Migrations\MigrationsPathsProvider;

/** Supplies migrations for the "reporting" connection only, as a module would for a connection named in config. */
final class ReportingMigrations implements MigrationsPathsProvider
{
    #[Override]
    public function migrationsPaths(string $connection): array
    {
        return $connection === 'reporting' ? ['Provided\Reporting' => 'provided/reporting'] : [];
    }
}
