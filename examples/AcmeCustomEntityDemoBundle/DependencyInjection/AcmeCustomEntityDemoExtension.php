<?php

declare(strict_types=1);

namespace Acme\Bundle\CustomEntityDemoBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

/**
 * Declares the demo custom entities. In an application you would usually put
 * this configuration in config/packages/danmyers_custom_entity.yml instead.
 */
final class AcmeCustomEntityDemoExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../Resources/config/custom_entities.yml');
        $container->prependExtensionConfig('danmyers_custom_entity', $config['danmyers_custom_entity']);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
    }
}
