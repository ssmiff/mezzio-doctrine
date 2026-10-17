<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use Laminas\ServiceManager\ServiceManager;
use Ssmiff\MezzioDoctrine\ConfigProvider;
use Ssmiff\MezzioDoctrine\Registry;
use Ssmiff\MezzioContainerHelpers\Services;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function file_put_contents;
use function mkdir;
use function preg_replace;

final class ConsoleTest extends TestCase
{
    /** @param array<array-key, mixed> $doctrine */
    private function app(array $doctrine = [], ?ServiceManager $container = null): ApplicationTester
    {
        $container ??= $this->container($doctrine);

        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setCommandLoader(new ContainerCommandLoader($container, new ConfigProvider()->commands()));
        $application->setDispatcher(Services::get($container, EventDispatcher::class, ConfigProvider::CLI_EVENT_DISPATCHER));

        return new ApplicationTester($application);
    }

    private static function squash(string $output): string
    {
        return preg_replace('/\s+/', ' ', $output) ?? $output;
    }

    public function testConnOptionSelectsPerConnectionMigrationsSettings(): void
    {
        $app = $this->app();

        $app->run(['command' => 'migrations:status', '--conn' => 'my-db'], ['decorated' => false]);

        self::assertStringContainsString('Database\Migrations\MyDb', $app->getDisplay());
        self::assertStringContainsString($this->base . '/database/migrations/my-db', $app->getDisplay());
    }

    public function testDefaultConnectionIsUsedWithoutTheOption(): void
    {
        $app = $this->app(['default' => 'reporting']);

        $app->run(['command' => 'migrations:status'], ['decorated' => false]);

        self::assertStringContainsString('Database\Migrations\Reporting', $app->getDisplay());
    }

    public function testMigrationsRunAgainstTheChosenConnectionOnly(): void
    {
        $container = $this->container([
            'connections' => ['reporting' => ['memory' => false, 'path' => $this->base . '/reporting.sqlite']],
            'migrations'  => ['connections' => ['reporting' => ['migrations_paths' => ['Reporting' => 'migrations/reporting']]]],
        ]);
        mkdir($this->base . '/migrations/reporting', 0775, true);
        file_put_contents($this->base . '/migrations/reporting/Version1.php', <<<'PHP'
            <?php
            namespace Reporting;
            use Doctrine\DBAL\Schema\Schema;
            use Doctrine\Migrations\AbstractMigration;
            final class Version1 extends AbstractMigration
            {
                public function up(Schema $schema): void { $this->addSql('CREATE TABLE stats (id INTEGER)'); }
            }
            PHP);

        $app = $this->app(container: $container);
        self::assertSame(0, $app->run(['command' => 'migrations:migrate', '--conn' => 'reporting', '-n' => true]));

        $registry = Services::get($container, Registry::class);
        self::assertTrue($registry->connection('reporting')->createSchemaManager()->tablesExist(['stats']));
        self::assertFalse($registry->connection('main')->createSchemaManager()->tablesExist(['stats']));
        self::assertFalse($registry->connection('main')->createSchemaManager()->tablesExist(['doctrine_migration_versions']));
    }

    public function testEmIsAcceptedAsASynonymForMigrations(): void
    {
        $app = $this->app();

        $app->run(['command' => 'migrations:status', '--em' => 'my-db'], ['decorated' => false]);

        self::assertStringContainsString('Database\Migrations\MyDb', $app->getDisplay());
    }

    public function testUnknownConnectionListsTheAvailableOnes(): void
    {
        $this->expectExceptionMessage('Unknown connection "nope". Available connections: main, reporting, my-db.');

        $this->app()->run(['command' => 'migrations:migrate', '--conn' => 'nope', '-n' => true]);
    }

    public function testOrmCommandsAcceptTheSameOption(): void
    {
        $app = $this->app(['entity_managers' => ['main' => ['mapping' => ['paths' => [__DIR__ . '/Fixture']]]]]);

        $app->run(['command' => 'orm:info', '--conn' => 'main'], ['decorated' => false]);
        self::assertStringContainsString('Found 1 mapped entities', self::squash($app->getDisplay()));

        $app->run(['command' => 'orm:info', '--conn' => 'reporting'], ['decorated' => false]);
        self::assertStringContainsString('do not have any mapped', self::squash($app->getDisplay()));
    }

    public function testConflictingConnAndEmAreRejected(): void
    {
        $this->expectExceptionMessage('Use either --conn or --em');

        $this->app()->run(['command' => 'orm:info', '--conn' => 'main', '--em' => 'reporting']);
    }

    public function testDiffRefusesConnectionsWithoutMapping(): void
    {
        $this->expectExceptionMessage('has no mapped entities');

        $this->app()->run(['command' => 'migrations:diff', '--conn' => 'reporting', '-n' => true]);
    }
}
