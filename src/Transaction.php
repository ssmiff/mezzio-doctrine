<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine;

interface Transaction
{
    /** @param callable(): void $work */
    public function run(callable $work): void;
}
