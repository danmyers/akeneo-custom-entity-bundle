<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Datagrid\MassAction;

use DanMyers\CustomEntityBundle\Export\QuickExportLauncher;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\Actions\MassActionInterface;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse;
use Oro\Bundle\PimDataGridBundle\Datasource\DatasourceInterface;
use Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\Handler\MassActionHandlerInterface;

/**
 * Launches the quick export job for the entities selected in the grid,
 * honouring "select all" and the active filters.
 */
class QuickExportHandler implements MassActionHandlerInterface
{
    public function __construct(
        private readonly QuickExportLauncher $launcher,
        private readonly HydratorInterface $objectIdHydrator,
    ) {
    }

    public function handle(DatagridInterface $datagrid, MassActionInterface $massAction)
    {
        $options = $massAction->getOptions();
        $customEntity = (string) $options->offsetGet('custom_entity');
        $format = (string) ($options->offsetGetByPath('[route_parameters][_format]') ?? 'csv');

        $datasource = $datagrid->getDatasource();
        if (!$datasource instanceof DatasourceInterface) {
            throw new \LogicException(sprintf('The datagrid "%s" must use a PIM datasource.', $datagrid->getName()));
        }
        $datasource->setHydrator($this->objectIdHydrator);
        $ids = array_values(array_map('intval', $datasource->getResults()));

        $jobExecution = $this->launcher->launch($customEntity, $ids, $format);

        return new MassActionResponse(true, 'danmyers_custom_entity.mass_action.quick_export.success', [
            'job_id' => $jobExecution->getId(),
        ]);
    }
}
