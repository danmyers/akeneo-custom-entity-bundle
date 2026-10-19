<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity\Repository;

use Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ReferenceDataRepository;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface;

/**
 * Default repository of custom entities.
 *
 * On top of Akeneo's reference data repository (select2 search, identifier
 * lookups) it provides the datagrid query builder, the mass action contract
 * and bulk lookups used by the connectors.
 */
class CustomEntityRepository extends ReferenceDataRepository implements MassActionRepositoryInterface
{
    /**
     * Select2 search used by reference data attributes in the product edit
     * form, the product grid filters and the custom entity select fields.
     *
     * Options: type ("id" or "code", the value returned as "id"), limit, page or offset,
     * dataLocale (translatable entities), identifiers (restrict to codes).
     *
     * @return array<array{id: int|string, text: string}>
     */
    public function findBySearch($search = null, array $options = [])
    {
        $qb = $this->createSearchQueryBuilder($search, $options);

        return array_map(
            static fn (array $row): array => ['id' => $row['id'], 'text' => (string) $row['text']],
            $qb->getQuery()->getArrayResult(),
        );
    }

    protected function createSearchQueryBuilder(?string $search, array $options): QueryBuilder
    {
        $alias = $this->getAlias();
        $idField = isset($options['type']) && 'code' === $options['type'] ? 'code' : 'id';
        $labelExpression = $this->getLabelExpression($options);

        $qb = $this->createQueryBuilder($alias);
        $this->joinLabel($qb, $options);

        if (null !== $labelExpression) {
            $qb->select(sprintf(
                "%1\$s.%2\$s AS id, CASE WHEN %3\$s IS NULL OR %3\$s = '' THEN CONCAT('[', %1\$s.code, ']') ELSE %3\$s END AS text",
                $alias,
                $idField,
                $labelExpression,
            ));
        } else {
            $qb->select(sprintf("%1\$s.%2\$s AS id, CONCAT('[', %1\$s.code, ']') AS text", $alias, $idField));
        }

        // every word must match the code or the label, in any order:
        // "wrangler 2025" finds "2025 Jeep Wrangler Unlimited (JL)"
        $words = null === $search ? [] : (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        foreach (array_values($words) as $index => $word) {
            $parameter = 'search_' . $index;
            $searchDql = sprintf('%s.code LIKE :%s', $alias, $parameter);
            if (null !== $labelExpression) {
                $searchDql .= sprintf(' OR %s LIKE :%s', $labelExpression, $parameter);
            }
            $qb->andWhere($searchDql)->setParameter($parameter, '%' . addcslashes($word, '%_') . '%');
        }

        if (!empty($options['identifiers']) && is_array($options['identifiers'])) {
            $qb->andWhere(sprintf('%s.code IN (:identifiers)', $alias))->setParameter('identifiers', $options['identifiers']);
        }

        $qb->orderBy(sprintf('%s.sortOrder', $alias), 'DESC');
        $entityClass = $this->getEntityName();
        $sortColumn = method_exists($entityClass, 'getSortOrderColumn') ? $entityClass::getSortOrderColumn() : 'code';
        if ('code' !== $sortColumn) {
            $qb->addOrderBy($this->getClassMetadata()->hasField($sortColumn) ? sprintf('%s.%s', $alias, $sortColumn) : ($labelExpression ?? sprintf('%s.code', $alias)));
        }
        $qb->addOrderBy(sprintf('%s.code', $alias));

        if (isset($options['limit'])) {
            $qb->setMaxResults((int) $options['limit']);
            if (isset($options['offset'])) {
                $qb->setFirstResult(max(0, (int) $options['offset']));
            } elseif (isset($options['page'])) {
                $qb->setFirstResult((int) $options['limit'] * max(0, (int) $options['page'] - 1));
            }
        }

        return $qb;
    }

    /**
     * DQL expression of the label, or null when the entity has no label.
     */
    protected function getLabelExpression(array $options): ?string
    {
        /** @var class-string<AbstractCustomEntity> $entityClass */
        $entityClass = $this->getEntityName();
        $labelProperty = $entityClass::getLabelProperty();

        if (null !== $labelProperty && $this->getClassMetadata()->hasField($labelProperty)) {
            return sprintf('%s.%s', $this->getAlias(), $labelProperty);
        }

        return null;
    }

    /**
     * Hook for repositories whose label lives in another table.
     */
    protected function joinLabel(QueryBuilder $qb, array $options): void
    {
    }

    /**
     * Query builder of the generated datagrids. Each row is a flat array:
     * id, code, sortOrder, created, updated, every scalar field, the code of
     * every single-valued association, and the custom entity name used by the
     * row action URLs.
     *
     * @param array{custom_entity?: string, locale?: string} $parameters
     */
    public function createDatagridQueryBuilder(array $parameters = []): QueryBuilder
    {
        $alias = $this->getAlias();
        $metadata = $this->getClassMetadata();

        $qb = $this->createQueryBuilder($alias)->select(sprintf('%s.id AS id', $alias));

        foreach ($metadata->getFieldNames() as $field) {
            if ('id' === $field) {
                continue;
            }
            $qb->addSelect(sprintf('%1$s.%2$s AS %2$s', $alias, $field));
        }

        foreach ($metadata->getAssociationMappings() as $field => $mapping) {
            if (!($mapping['type'] & ClassMetadataInfo::TO_ONE) || !$mapping['isOwningSide']) {
                continue;
            }
            $targetMetadata = $this->getEntityManager()->getClassMetadata($mapping['targetEntity']);
            if (!$targetMetadata->hasField('code')) {
                continue;
            }
            $joinAlias = 'assoc_' . $field;
            $qb->leftJoin(sprintf('%s.%s', $alias, $field), $joinAlias)
                ->addSelect(sprintf('%s.code AS %s', $joinAlias, $field));
        }

        if (isset($parameters['custom_entity'])) {
            $qb->addSelect(sprintf("'%s' AS customEntityName", addslashes($parameters['custom_entity'])));
        }

        return $qb;
    }

    public function applyMassActionParameters($qb, $inset, array $values)
    {
        $rootAlias = $qb->getRootAliases()[0];

        if ([] !== $values) {
            $qb->andWhere(
                $qb->expr()->{$inset ? 'in' : 'notIn'}(sprintf('%s.id', $rootAlias), ':mass_action_ids'),
            )->setParameter('mass_action_ids', $values);
        }

        $qb->resetDQLPart('orderBy');
        $qb->setMaxResults(null);
    }

    /**
     * @param int[] $ids
     *
     * @return AbstractCustomEntity[]
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder($this->getAlias())
            ->where(sprintf('%s.id IN (:ids)', $this->getAlias()))
            ->setParameter('ids', $ids)
            ->orderBy(sprintf('%s.code', $this->getAlias()))
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     *
     * @return string[]
     */
    public function findCodesByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return array_column(
            $this->createQueryBuilder($this->getAlias())
                ->select(sprintf('%s.code', $this->getAlias()))
                ->where(sprintf('%s.id IN (:ids)', $this->getAlias()))
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getScalarResult(),
            'code',
        );
    }

    /**
     * @param string[] $codes
     *
     * @return AbstractCustomEntity[]
     */
    public function findByCodes(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        return $this->createQueryBuilder($this->getAlias())
            ->where(sprintf('%s.code IN (:codes)', $this->getAlias()))
            ->setParameter('codes', $codes)
            ->getQuery()
            ->getResult();
    }

    /**
     * Iterates over every entity in batches, ordered by id, to keep memory
     * usage flat during exports.
     *
     * @return \Generator<AbstractCustomEntity>
     */
    public function iterateAll(int $batchSize = 100): \Generator
    {
        $lastId = 0;
        do {
            $batch = $this->createQueryBuilder($this->getAlias())
                ->where(sprintf('%s.id > :last_id', $this->getAlias()))
                ->setParameter('last_id', $lastId)
                ->orderBy(sprintf('%s.id', $this->getAlias()))
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();

            foreach ($batch as $entity) {
                $lastId = $entity->getId();
                yield $entity;
            }
        } while (count($batch) === $batchSize);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder($this->getAlias())
            ->select(sprintf('COUNT(%s.id)', $this->getAlias()))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
