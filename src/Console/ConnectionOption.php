<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use Ssmiff\MezzioDoctrine\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

use function sprintf;

final readonly class ConnectionOption
{
    public const string NAME = 'conn';

    public static function addTo(Command $command, Registry $registry): void
    {
        if ($command->getDefinition()->hasOption(self::NAME)) {
            return;
        }

        $command->addOption(
            self::NAME,
            null,
            InputOption::VALUE_REQUIRED,
            sprintf(
                'The name of the connection to use. <comment>[default: %s]</comment>',
                $registry->getDefaultConnectionName(),
            ),
        );
    }
}
