<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Datagrid\MassAction;

use DanMyers\CustomEntityBundle\Exception\NonRemovableEntityException;
use DanMyers\CustomEntityBundle\Manager\ManagerRegistry;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Usage\EntityUsageChecker;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;
use Oro\Bundle\PimDataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\MassActionHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Deletes the selected custom entities through their manager, one by one,
 * so that history entries are written. Nothing is deleted when one of the
 * selected entities is still used by products.
 */
class MassDeleteHandler implements MassActionHandlerInterface
{
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly ManagerRegistry $managerRegistry,
        private readonly EntityUsageChecker $usageChecker,
        private readonly HydratorInterface $objectIdHydrator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function handle(DatagridInterface $datagrid, MassActionInterface $massAction)
    {
        $definition = $this->registry->get((string) $massAction->getOptions()->offsetGet('custom_entity'));
        $manager = $this->managerRegistry->getForDefinition($definition);

        $datasource = $datagrid->getDatasource();
        if (!$datasource instanceof DatasourceInterface) {
            throw new \LogicException(sprintf('The datagrid "%s" must use a PIM datasource.', $datagrid->getName()));
        }
        $datasource->setHydrator($this->objectIdHydrator);
        $ids = array_map('intval', $datasource->getResults());

        $entities = array_values(array_filter(array_map(
            static fn (int $id) => $manager->find($definition, $id),
            $ids,
        )));

        try {
            foreach ($entities as $entity) {
                $this->usageChecker->assertNotUsed($entity);
            }
            foreach ($entities as $entity) {
                $manager->remove($entity);
            }
        } catch (NonRemovableEntityException $exception) {
            return new MassActionResponse(false, $exception->getMessage());
        }

        $successMessage = $massAction->getOptions()->offsetGetByPath('[messages][success]') ?? 'danmyers_custom_entity.mass_action.delete.success';

        return new MassActionResponse(
            true,
            $this->translator->trans($successMessage, ['%count%' => count($entities)]),
            ['count' => count($entities)],
        );
    }
}
