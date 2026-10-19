<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit;

use DanMyers\CustomEntityBundle\DependencyInjection\Configuration;
use DanMyers\CustomEntityBundle\DependencyInjection\DanMyersCustomEntityExtension;
use Symfony\Component\Config\Definition\Processor;

/**
 * Builds the normalized entity configuration the bundle works with, from raw
 * "danmyers_custom_entity.entities" YAML-like arrays.
 */
trait EntityConfigTrait
{
    /**
     * @param array<string, array> $entities
     *
     * @return array<string, array>
     */
    private static function normalizedEntities(array $entities): array
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['entities' => $entities]]);

        $normalized = [];
        foreach ($config['entities'] as $name => $entity) {
            $normalized[$name] = DanMyersCustomEntityExtension::normalizeEntity($name, $entity);
        }

        return $normalized;
    }
}
