<?php

declare(strict_types=1);

namespace Ssmiff\MezzioDoctrine\Attribute;

use Attribute;
use Override;
use Psr\Container\ContainerInterface;
use Ssmiff\MezzioContainerHelpers\Attribute\ParameterSource;
use Ssmiff\MezzioDoctrine\ServiceId;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class UseConnection implements ParameterSource
{
    public function __construct(public string $name)
    {
    }

    #[Override]
    public function resolve(ContainerInterface $container): mixed
    {
        return $container->get(ServiceId::connection($this->name));
    }
}
