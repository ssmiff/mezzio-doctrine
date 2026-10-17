<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use Override;
use Ssmiff\MezzioDoctrine\Registry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;

#[AsCommand(name: 'db:connections', description: 'List the configured database connections.')]
final class ConnectionsCommand extends Command
{
    public function __construct(private readonly Registry $registry)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Connection', 'Default', 'Driver', 'Target']);

        foreach (array_keys($this->registry->getConnectionNames()) as $name) {
            $params = $this->registry->connectionSettings($name);
            $table->addRow([
                $name,
                $name === $this->registry->getDefaultConnectionName() ? 'yes' : '',
                $params->string('driver') ?? ($params->has('url') ? 'url' : '?'),
                $params->string('path') ?? $params->string('dbname') ?? ($params->bool('memory') ? ':memory:' : ''),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
