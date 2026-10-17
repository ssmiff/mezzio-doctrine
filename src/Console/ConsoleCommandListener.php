<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

interface ConsoleCommandListener
{
    public function __invoke(ConsoleCommandEvent $event): void;
}
