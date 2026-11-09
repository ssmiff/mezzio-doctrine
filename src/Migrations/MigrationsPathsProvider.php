<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Migrations;

/**
 * Supplies migrations for a connection whose name is only known at runtime, such as one chosen in the app's
 * config. List the service id under doctrine.migrations.paths_providers.
 */
interface MigrationsPathsProvider
{
    /**
     * @return array<string, string> migrations namespace => directory, for the given connection; [] for none
     */
    public function migrationsPaths(string $connection): array;
}
