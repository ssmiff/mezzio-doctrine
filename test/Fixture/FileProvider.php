<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test\Fixture;

use Override;
use Ssmiff\MezzioDoctrine\ParamsProvider;

final class FileProvider implements ParamsProvider
{
    public static string $path = ':memory:';

    #[Override]
    public function params(): array
    {
        return ['driver' => 'pdo_sqlite', 'path' => self::$path];
    }
}
