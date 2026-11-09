<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test;

use InvalidArgumentException;
use Laminas\ServiceManager\ServiceManager;
use Override;
use Ssmiff\MezzioContainerHelpers\Services;
use Ssmiff\MezzioDoctrine\ConfigProvider;
use Ssmiff\MezzioDoctrine\Registry;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function preg_replace;

final class RunSqlTest extends TestCase
{
    private ServiceManager $services;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->services = $this->container();
    }

    /** Runs db:run-sql and returns its output with runs of whitespace squashed. */
    private function sql(string $sql, array $options = []): string
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setCommandLoader(new ContainerCommandLoader($this->services, new ConfigProvider()->commands()));
        $application->setDispatcher(
            Services::get($this->services, EventDispatcher::class, ConfigProvider::CLI_EVENT_DISPATCHER),
        );

        $tester = new ApplicationTester($application);
        $status = $tester->run(['command' => 'db:run-sql', 'sql' => $sql, ...$options], ['decorated' => false]);
        self::assertSame(0, $status);

        return preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '';
    }

    public function testStatementsReportTheRowsTheyChangeAndQueriesShowTheirRows(): void
    {
        $created = $this->sql('CREATE TABLE things (id INTEGER, name TEXT)');
        self::assertStringContainsString('0 row(s) affected.', $created);
        self::assertStringContainsString(
            '2 row(s) affected.',
            $this->sql("INSERT INTO things VALUES (1, 'one'), (2, NULL)"),
        );

        $output = $this->sql('SELECT id, name FROM things ORDER BY id');

        self::assertStringContainsString('| id | name |', $output);
        self::assertStringContainsString('| 1 | one |', $output);
        self::assertStringContainsString('| 2 | NULL |', $output);
        self::assertStringContainsString('2 row(s).', $output);
    }

    public function testQueriesAreRecognisedWhateverTheirCaseOrLeadingSpace(): void
    {
        self::assertStringContainsString('| answer |', $this->sql("  select 42 as answer"));
        self::assertStringContainsString('| n |', $this->sql('WITH t(n) AS (SELECT 1) SELECT n FROM t'));
    }

    public function testAnEmptyResultSaysSo(): void
    {
        $this->sql('CREATE TABLE things (id INTEGER)');

        self::assertStringContainsString('No rows.', $this->sql('SELECT * FROM things'));
    }

    public function testForceFetchShowsTheRowsOfAStatement(): void
    {
        $this->sql('CREATE TABLE things (id INTEGER, name TEXT)');

        $output = $this->sql("INSERT INTO things VALUES (7, 'seven') RETURNING id, name", ['--force-fetch' => true]);

        self::assertStringContainsString('| 7 | seven |', $output);
    }

    public function testConnRunsItOnAnotherConnectionOnly(): void
    {
        $this->sql('CREATE TABLE only_in_reporting (id INTEGER)', ['--conn' => 'reporting']);

        $registry = Services::get($this->services, Registry::class);
        self::assertTrue($registry->connection('reporting')->createSchemaManager()->tablesExist(['only_in_reporting']));
        self::assertFalse($registry->connection('main')->createSchemaManager()->tablesExist(['only_in_reporting']));
    }

    public function testAnUnknownConnectionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown connection "nope"');

        $this->sql('SELECT 1', ['--conn' => 'nope']);
    }

    public function testEmptySqlIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sql('   ');
    }

    public function testItDoesNotBuildAnEntityManager(): void
    {
        $this->sql('SELECT 1', ['--conn' => 'reporting']);

        $managers = new \ReflectionProperty(Registry::class, 'instances');
        $built    = $managers->getValue(Services::get($this->services, Registry::class));

        self::assertIsArray($built);
        self::assertArrayHasKey('connection.reporting', $built);
        self::assertArrayNotHasKey('manager.reporting', $built);
    }
}
