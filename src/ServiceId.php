<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine;

final readonly class ServiceId
{
    public const string CONNECTION     = 'doctrine.connection.';
    public const string ENTITY_MANAGER = 'doctrine.entity_manager.';

    public static function connection(string $name): string
    {
        return self::CONNECTION . $name;
    }

    public static function entityManager(string $name): string
    {
        return self::ENTITY_MANAGER . $name;
    }
}
