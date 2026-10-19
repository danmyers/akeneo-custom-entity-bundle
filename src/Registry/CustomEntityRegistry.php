<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Registry;

use DanMyers\CustomEntityBundle\Exception\UnknownCustomEntityException;
use Doctrine\Common\Util\ClassUtils;

/**
 * Lists every configured custom entity type.
 */
class CustomEntityRegistry
{
    /** @var array<string, CustomEntityDefinition> */
    private array $definitions = [];

    /** @var array<string, string> class => name */
    private array $namesByClass = [];

    /**
     * @param array<string, array> $entities normalized configuration, keyed by name
     */
    public function __construct(array $entities)
    {
        foreach ($entities as $name => $config) {
            $definition = CustomEntityDefinition::fromArray($config);
            $this->definitions[$name] = $definition;
            $this->namesByClass[$definition->getClass()] = $name;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): CustomEntityDefinition
    {
        if (!isset($this->definitions[$name])) {
            throw UnknownCustomEntityException::fromName($name);
        }

        return $this->definitions[$name];
    }

    /**
     * @return array<string, CustomEntityDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Finds the definition of an entity object or class name, following
     * Doctrine proxies and subclasses.
     */
    public function findByClass(object|string $classOrObject): ?CustomEntityDefinition
    {
        /** @var class-string $className */
        $className = is_object($classOrObject) ? get_class($classOrObject) : $classOrObject;
        $class = ClassUtils::getRealClass($className);

        if (isset($this->namesByClass[$class])) {
            return $this->definitions[$this->namesByClass[$class]];
        }

        foreach ($this->definitions as $definition) {
            if (is_a($class, $definition->getClass(), true)) {
                return $definition;
            }
        }

        return null;
    }
}
