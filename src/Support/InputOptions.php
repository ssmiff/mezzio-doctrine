<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Support;

use Symfony\Component\Console\Input\InputInterface;

use function is_string;

final readonly class InputOptions
{
    public static function string(InputInterface $input, string $name): ?string
    {
        return $input->hasOption($name) ? self::asString($input->getOption($name)) : null;
    }

    private static function asString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
