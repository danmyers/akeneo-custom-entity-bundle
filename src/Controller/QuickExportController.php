<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Controller;

use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Oro\Bundle\PimDataGridBundle\Extension\MassAction\MassActionDispatcher;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoint of the datagrid "quick export" mass actions. The selection
 * (including "select all" with filters) is resolved by Akeneo's mass action
 * dispatcher, which hands over to the QuickExportHandler.
 */
class QuickExportController
{
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly MassActionParametersParser $parametersParser,
        private readonly MassActionDispatcher $massActionDispatcher,
        private readonly SecurityFacade $securityFacade,
    ) {
    }

    public function exportAction(Request $request, string $customEntityName): Response
    {
        if (!$this->registry->has($customEntityName)) {
            throw new NotFoundHttpException(sprintf('Unknown custom entity "%s".', $customEntityName));
        }
        $definition = $this->registry->get($customEntityName);
        if (!$definition->isActionEnabled('quick_export')) {
            throw new NotFoundHttpException();
        }
        if (!$this->securityFacade->isGranted($definition->getAcl('index'))) {
            throw new AccessDeniedHttpException();
        }

        $parameters = $this->parametersParser->parse($request);
        $response = $this->massActionDispatcher->dispatch($parameters);

        if (!$response->isSuccessful()) {
            return new JsonResponse(['message' => $response->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($response->getOptions());
    }
}
