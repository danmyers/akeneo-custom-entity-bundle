<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Manager;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Persistence and lifecycle operations of custom entities.
 *
 * Register an alternative implementation with the
 * "danmyers_custom_entity.manager" tag and an "alias" attribute, then select
 * it per entity with the "manager" configuration key.
 */
interface CustomEntityManagerInterface
{
    /**
     * Instantiates a new entity and applies the given standard format data.
     */
    public function create(CustomEntityDefinition $definition, array $data = []): AbstractCustomEntity;

    public function find(CustomEntityDefinition $definition, int $id): ?AbstractCustomEntity;

    public function findByCode(CustomEntityDefinition $definition, string $code): ?AbstractCustomEntity;

    /**
     * @return AbstractCustomEntity[]
     */
    public function findAll(CustomEntityDefinition $definition): array;

    /**
     * Applies standard format data to an entity.
     */
    public function update(AbstractCustomEntity $entity, array $data): void;

    public function validate(AbstractCustomEntity $entity): ConstraintViolationListInterface;

    public function save(AbstractCustomEntity $entity, array $options = []): void;

    /**
     * @throws \DanMyers\CustomEntityBundle\Exception\NonRemovableEntityException when the entity is used by products
     */
    public function remove(AbstractCustomEntity $entity, array $options = []): void;

    public function normalize(AbstractCustomEntity $entity, string $format = 'internal_api', array $context = []): array;
}
