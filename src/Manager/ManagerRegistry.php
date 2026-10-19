<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Manager;

use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use Psr\Container\ContainerInterface;

/**
 * Resolves the manager of a custom entity from its "manager" alias.
 */
class ManagerRegistry
{
    /**
     * @param ContainerInterface $managers service locator of the services tagged "danmyers_custom_entity.manager", keyed by alias
     */
    public function __construct(private readonly ContainerInterface $managers)
    {
    }

    public function get(string $alias): CustomEntityManagerInterface
    {
        if (!$this->managers->has($alias)) {
            throw new \InvalidArgumentException(sprintf(
                'No custom entity manager is registered with the alias "%s". Tag a service with "danmyers_custom_entity.manager" and this alias.',
                $alias,
            ));
        }

        return $this->managers->get($alias);
    }

    public function getForDefinition(CustomEntityDefinition $definition): CustomEntityManagerInterface
    {
        return $this->get($definition->getManagerAlias());
    }
}
