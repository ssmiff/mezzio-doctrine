<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Console;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\EntityManagerProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\UnknownManagerException;
use Override;
use Ssmiff\MezzioDoctrine\Registry;

use function array_keys;

final class RegistryEntityManagerProvider implements EntityManagerProvider
{
    public private(set) string|null $selected = null;

    public function __construct(private readonly Registry $registry)
    {
    }

    public function select(string $name): void
    {
        $this->assertKnown($name);
        $this->selected = $name;
    }

    #[Override]
    public function getDefaultManager(): EntityManagerInterface
    {
        return $this->registry->entityManager($this->selected);
    }

    #[Override]
    public function getManager(string $name): EntityManagerInterface
    {
        $this->assertKnown($name);

        return $this->registry->entityManager($name);
    }

    private function assertKnown(string $name): void
    {
        $names = $this->registry->getManagerNames();
        if (! isset($names[$name])) {
            throw UnknownManagerException::unknownManager($name, array_keys($names));
        }
    }
}
