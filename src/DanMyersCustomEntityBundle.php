<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle;

use DanMyers\CustomEntityBundle\DependencyInjection\Compiler\RegisterCustomEntitiesPass;
use DanMyers\CustomEntityBundle\DependencyInjection\DanMyersCustomEntityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Custom entities (reference data) for Akeneo PIM.
 */
final class DanMyersCustomEntityBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterCustomEntitiesPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new DanMyersCustomEntityExtension();
    }

    public function getPath(): string
    {
        return __DIR__;
    }
}
