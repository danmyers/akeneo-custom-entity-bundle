<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\Reader;

use Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader;
use DanMyers\CustomEntityBundle\Entity\Repository\CustomEntityRepository;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads the entities of the custom entity type given by the "custom_entity"
 * job parameter. When an "ids" job parameter is present (quick export from
 * the grid), only those entities are read.
 */
class CustomEntityReader extends AbstractReader
{
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function getResults(): \ArrayIterator
    {
        $parameters = $this->stepExecution->getJobParameters();
        $definition = $this->registry->get((string) $parameters->get('custom_entity'));
        $repository = $this->entityManager->getRepository($definition->getClass());

        $ids = $parameters->has('ids') ? $parameters->get('ids') : null;
        if (is_array($ids)) {
            $entities = $repository instanceof CustomEntityRepository
                ? $repository->findByIds(array_map('intval', $ids))
                : $repository->findBy(['id' => $ids], ['code' => 'ASC']);

            return new \ArrayIterator($entities);
        }

        return new \ArrayIterator($repository->findBy([], ['code' => 'ASC']));
    }
}
