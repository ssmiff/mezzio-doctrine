<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Test\Fixture;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class Widget
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        public int $id = 0,
    ) {}
}
