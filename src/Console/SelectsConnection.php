<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

/**
 * A command of this module that works on one connection. The --conn listener tells it which, after other
 * console listeners have had their say (tenancy's --tenant, for one, switches it to the tenant connection).
 * The command must not read --conn itself: Symfony parses the input again before execute(), which drops
 * anything a listener changed.
 */
interface SelectsConnection
{
    public function selectConnection(string $name): void;
}
