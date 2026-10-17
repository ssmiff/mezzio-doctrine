<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine;

interface ParamsProvider
{
    /** @return array<string, mixed> */
    public function params(): array;
}
