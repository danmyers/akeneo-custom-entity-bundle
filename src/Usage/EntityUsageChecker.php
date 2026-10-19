<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Usage;

use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Exception\NonRemovableEntityException;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;

/**
 * Tells whether a custom entity is used by products or product models
 * through reference data attributes.
 */
class EntityUsageChecker
{
    /** @var array<string, AttributeInterface[]> */
    private array $attributesByReferenceData = [];

    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly ProductQueryBuilderFactoryInterface $pqbFactory,
    ) {
    }

    /**
     * @throws NonRemovableEntityException
     */
    public function assertNotUsed(AbstractCustomEntity $entity): void
    {
        $definition = $this->registry->findByClass($entity);
        if (null === $definition || !$definition->isReferenceData()) {
            return;
        }

        foreach ($this->getAttributesUsing($definition->getName()) as $attribute) {
            $count = $this->pqbFactory->create()
                ->addFilter($attribute->getCode(), Operators::IN_LIST, [$entity->getCode()])
                ->execute()
                ->count();

            if (0 < $count) {
                throw NonRemovableEntityException::usedByProducts((string) $entity->getCode(), $count, $attribute->getCode());
            }
        }
    }

    /**
     * Reference data attributes pointing to the given custom entity type.
     *
     * @return AttributeInterface[]
     */
    public function getAttributesUsing(string $referenceDataName): array
    {
        if (!isset($this->attributesByReferenceData[$referenceDataName])) {
            $attributes = $this->attributeRepository->findBy([
                'type' => [AttributeTypes::REFERENCE_DATA_SIMPLE_SELECT, AttributeTypes::REFERENCE_DATA_MULTI_SELECT],
            ]);

            $this->attributesByReferenceData[$referenceDataName] = array_values(array_filter(
                $attributes,
                static fn (AttributeInterface $attribute): bool => $attribute->getReferenceDataName() === $referenceDataName,
            ));
        }

        return $this->attributesByReferenceData[$referenceDataName];
    }
}
