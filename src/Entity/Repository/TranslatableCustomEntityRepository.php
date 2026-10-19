<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Entity\Repository;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository of translatable custom entities: labels are read from the
 * translation of the requested locale.
 */
class TranslatableCustomEntityRepository extends CustomEntityRepository
{
    public const DEFAULT_LOCALE = 'en_US';
    private const TRANSLATION_ALIAS = 'translation';

    /**
     * Same rows as the parent, plus "label" in the locale given by the
     * datagrid "localeCode" parameter.
     */
    public function createDatagridQueryBuilder(array $parameters = []): QueryBuilder
    {
        $qb = parent::createDatagridQueryBuilder($parameters);

        $qb->leftJoin(
            sprintf('%s.translations', $this->getAlias()),
            self::TRANSLATION_ALIAS,
            Join::WITH,
            sprintf('%s.locale = :localeCode', self::TRANSLATION_ALIAS),
        )->addSelect(sprintf('%s.label AS label', self::TRANSLATION_ALIAS));

        $qb->setParameter('localeCode', $parameters['locale'] ?? null);

        return $qb;
    }

    protected function getLabelExpression(array $options): ?string
    {
        return sprintf('%s.label', self::TRANSLATION_ALIAS);
    }

    protected function joinLabel(QueryBuilder $qb, array $options): void
    {
        $qb->leftJoin(
            sprintf('%s.translations', $this->getAlias()),
            self::TRANSLATION_ALIAS,
            Join::WITH,
            sprintf('%s.locale = :search_locale', self::TRANSLATION_ALIAS),
        )->setParameter('search_locale', $options['dataLocale'] ?? $options['locale'] ?? self::DEFAULT_LOCALE);
    }
}
