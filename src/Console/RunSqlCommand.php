<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use InvalidArgumentException;
use Override;
use Ssmiff\MezzioDoctrine\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;
use function count;
use function is_bool;
use function is_scalar;
use function is_string;
use function json_encode;
use function preg_match;
use function sprintf;
use function trim;

use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Runs one SQL statement on a connection: rows for a query, the number of changed rows otherwise.
 *
 * It runs whatever it is given, with the connection's full rights.
 */
#[AsCommand(name: 'db:run-sql', description: 'Run an SQL statement on a connection and show the result.')]
final class RunSqlCommand extends Command implements SelectsConnection
{
    /** Statements that return rows: shown as a table. */
    private const string QUERY = '/^\s*(SELECT|WITH|SHOW|EXPLAIN|DESCRIBE|DESC|PRAGMA|VALUES|TABLE)\b/i';

    private ?string $connection = null;

    public function __construct(private readonly Registry $registry)
    {
        parent::__construct();
    }

    #[Override]
    public function selectConnection(string $name): void
    {
        $this->connection = $name;
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('sql', InputArgument::REQUIRED, 'The SQL statement to run.')
            ->addOption(
                'force-fetch',
                null,
                InputOption::VALUE_NONE,
                'Show the rows even when the statement does not look like a query, e.g. INSERT ... RETURNING.',
            );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = self::statement($input->getArgument('sql'));
        if ($sql === '') {
            throw new InvalidArgumentException('Give the SQL statement to run.');
        }

        // Chosen by the --conn listener; without one (a bare Application), the default connection. Consumed, so a
        // shared command instance never carries one run's choice into the next.
        $name             = $this->connection ?? $this->registry->getDefaultConnectionName();
        $this->connection = null;
        $connection       = $this->registry->connection($name);

        if ($input->getOption('force-fetch') !== true && preg_match(self::QUERY, $sql) !== 1) {
            $affected = $connection->executeStatement($sql);
            $output->writeln(sprintf('%d row(s) affected.', $affected));

            return Command::SUCCESS;
        }

        $rows = $connection->fetchAllAssociative($sql);

        if ($rows === []) {
            $output->writeln('No rows.');

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(array_keys($rows[0]));
        foreach ($rows as $row) {
            $table->addRow(array_map(self::cell(...), $row));
        }
        $table->render();

        $output->writeln(sprintf('%d row(s).', count($rows)));

        return Command::SUCCESS;
    }

    private static function statement(mixed $argument): string
    {
        return is_string($argument) ? trim($argument) : '';
    }

    private static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
        };
    }
}
