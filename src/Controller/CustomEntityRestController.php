<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Controller;

use Akeneo\Pim\Enrichment\Component\Product\Repository\ReferenceDataRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\PropertyException;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Event\CustomEntityActionEvent;
use DanMyers\CustomEntityBundle\Event\CustomEntityEvents;
use DanMyers\CustomEntityBundle\Exception\NonRemovableEntityException;
use DanMyers\CustomEntityBundle\Manager\CustomEntityManagerInterface;
use DanMyers\CustomEntityBundle\Manager\ManagerRegistry;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Internal REST API used by the custom entity screens.
 */
class CustomEntityRestController
{
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly ManagerRegistry $managerRegistry,
        private readonly SecurityFacade $securityFacade,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NormalizerInterface $violationNormalizer,
        private readonly UserContext $userContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly FieldSchemaProvider $schemaProvider,
    ) {
    }

    /**
     * Lists the configured custom entity types the user can see.
     */
    public function listTypesAction(): JsonResponse
    {
        $types = [];
        foreach ($this->registry->all() as $definition) {
            if (!$this->securityFacade->isGranted($definition->getAcl('index'))) {
                continue;
            }
            $types[$definition->getName()] = [
                'name' => $definition->getName(),
                'label' => $definition->getLabel(),
                'referenceData' => $definition->getReferenceDataType(),
                'acl' => $definition->getAcls(),
                'actions' => $definition->getActions(),
                'forms' => [
                    'index' => $definition->getIndexForm(),
                    'create' => $definition->getCreateForm(),
                    'edit' => $definition->getEditForm(),
                ],
                'datagrid' => $definition->getDatagrid(),
                'menu' => $definition->isInMenu(),
                'menuPosition' => $definition->getMenuPosition(),
                'menuItem' => sprintf('danmyers-custom-entity-menu-%s', str_replace('_', '-', $definition->getName())),
                'translatable' => $this->schemaProvider->isTranslatable($definition),
                'fields' => array_values(array_map(
                    static fn ($field): array => $field->toArray(),
                    $this->schemaProvider->getFields($definition),
                )),
            ];
        }

        return new JsonResponse($types);
    }

    public function listAction(Request $request, string $customEntityName): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessGranted($definition, 'index');

        return $this->run(CustomEntityEvents::ACTION_LIST, $definition, $request, null, function () use ($definition): Response {
            $manager = $this->getManager($definition);

            return new JsonResponse(array_map(
                fn (AbstractCustomEntity $entity): array => $manager->normalize($entity, 'internal_api', $this->getNormalizationContext()),
                $manager->findAll($definition),
            ));
        });
    }

    /**
     * Select2 search used by the select fields of the custom entity forms:
     * {"results": [{"id": "<code>", "text": "<label>"}], "more": bool}.
     */
    public function searchAction(Request $request, string $customEntityName): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessGranted($definition, 'index');

        return $this->run(CustomEntityEvents::ACTION_SEARCH, $definition, $request, null, function () use ($request, $definition): Response {
            // accepts both top level parameters and the "options[...]" sent by
            // Akeneo's async select fields
            $query = $request->query->all();
            $params = array_merge($query, is_array($query['options'] ?? null) ? $query['options'] : []);

            $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
            $page = max(1, (int) ($params['page'] ?? 1));
            $identifiers = $params['identifiers'] ?? [];
            if (is_string($identifiers)) {
                $identifiers = '' === $identifiers ? [] : explode(',', $identifiers);
            }

            $options = [
                'type' => 'code',
                'dataLocale' => $params['locale'] ?? $params['catalogLocale'] ?? $this->userContext->getCurrentLocaleCode(),
                'identifiers' => array_values($identifiers),
            ];
            if ([] === $identifiers) {
                // one extra row tells whether there is a next page
                $options['limit'] = $limit + 1;
                $options['offset'] = $limit * ($page - 1);
            }

            $repository = $this->entityManager->getRepository($definition->getClass());
            if (!$repository instanceof ReferenceDataRepositoryInterface) {
                throw new \LogicException(sprintf('The repository of "%s" must implement %s.', $definition->getClass(), ReferenceDataRepositoryInterface::class));
            }
            $results = $repository->findBySearch($params['search'] ?? null, $options);

            $more = [] === $identifiers && count($results) > $limit;

            return new JsonResponse([
                'results' => $more ? array_slice($results, 0, $limit) : $results,
                'more' => $more,
            ]);
        });
    }

    public function getAction(Request $request, string $customEntityName, int $id): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessGranted($definition, 'index');
        $entity = $this->findOr404($definition, $id);

        return $this->run(CustomEntityEvents::ACTION_GET, $definition, $request, $entity, fn (): Response => new JsonResponse(
            $this->getManager($definition)->normalize($entity, 'internal_api', $this->getNormalizationContext()),
        ));
    }

    public function createAction(Request $request, string $customEntityName): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessActionAllowed($definition, 'create');

        return $this->run(CustomEntityEvents::ACTION_CREATE, $definition, $request, null, function () use ($request, $definition): Response {
            $manager = $this->getManager($definition);
            $data = $this->decodeContent($request);

            try {
                $entity = $manager->create($definition, $data);
            } catch (PropertyException $exception) {
                return new JsonResponse(['values' => [$this->propertyExceptionToViolation($exception)]], Response::HTTP_BAD_REQUEST);
            }

            $violations = $manager->validate($entity);
            if (0 < $violations->count()) {
                return new JsonResponse(['values' => $this->normalizeViolations($violations, $entity)], Response::HTTP_BAD_REQUEST);
            }

            $manager->save($entity);

            return new JsonResponse(
                array_merge(
                    $manager->normalize($entity, 'internal_api', $this->getNormalizationContext()),
                    ['id' => $entity->getId(), 'customEntityName' => $definition->getName()],
                ),
                Response::HTTP_CREATED,
            );
        });
    }

    public function updateAction(Request $request, string $customEntityName, int $id): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessActionAllowed($definition, 'edit');
        $entity = $this->findOr404($definition, $id);

        return $this->run(CustomEntityEvents::ACTION_UPDATE, $definition, $request, $entity, function () use ($request, $definition, $entity): Response {
            $manager = $this->getManager($definition);
            $data = $this->decodeContent($request);
            unset($data['code']);

            try {
                $manager->update($entity, $data);
            } catch (PropertyException $exception) {
                return new JsonResponse([$this->propertyExceptionToViolation($exception)], Response::HTTP_BAD_REQUEST);
            }

            $violations = $manager->validate($entity);
            if (0 < $violations->count()) {
                return new JsonResponse($this->normalizeViolations($violations, $entity), Response::HTTP_BAD_REQUEST);
            }

            $manager->save($entity);

            return new JsonResponse($manager->normalize($entity, 'internal_api', $this->getNormalizationContext()));
        });
    }

    public function deleteAction(Request $request, string $customEntityName, int $id): Response
    {
        $definition = $this->getDefinition($customEntityName);
        $this->denyUnlessActionAllowed($definition, 'delete');
        $entity = $this->findOr404($definition, $id);

        return $this->run(CustomEntityEvents::ACTION_DELETE, $definition, $request, $entity, function () use ($definition, $entity): Response {
            try {
                $this->getManager($definition)->remove($entity);
            } catch (NonRemovableEntityException $exception) {
                return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_PRECONDITION_FAILED);
            }

            return new Response(null, Response::HTTP_NO_CONTENT);
        });
    }

    private function getNormalizationContext(): array
    {
        return array_merge($this->userContext->toArray(), [
            'catalogLocale' => $this->userContext->getCurrentLocaleCode(),
        ]);
    }

    /**
     * Wraps an action with the pre/post action events.
     */
    private function run(string $action, CustomEntityDefinition $definition, Request $request, ?AbstractCustomEntity $entity, callable $handler): Response
    {
        $preEvent = new CustomEntityActionEvent($action, $definition, $request, $entity);
        $this->eventDispatcher->dispatch($preEvent, CustomEntityEvents::PRE_ACTION);
        if (null !== $preEvent->getResponse()) {
            return $preEvent->getResponse();
        }

        $response = $handler();

        $postEvent = new CustomEntityActionEvent($action, $definition, $request, $entity, $response);
        $this->eventDispatcher->dispatch($postEvent, CustomEntityEvents::POST_ACTION);

        return $postEvent->getResponse() ?? $response;
    }

    private function getDefinition(string $customEntityName): CustomEntityDefinition
    {
        if (!$this->registry->has($customEntityName)) {
            throw new NotFoundHttpException(sprintf('Unknown custom entity "%s".', $customEntityName));
        }

        return $this->registry->get($customEntityName);
    }

    private function getManager(CustomEntityDefinition $definition): CustomEntityManagerInterface
    {
        return $this->managerRegistry->getForDefinition($definition);
    }

    private function findOr404(CustomEntityDefinition $definition, int $id): AbstractCustomEntity
    {
        $entity = $this->getManager($definition)->find($definition, $id);
        if (null === $entity) {
            throw new NotFoundHttpException(sprintf('No "%s" entity with id %d.', $definition->getName(), $id));
        }

        return $entity;
    }

    private function denyUnlessGranted(CustomEntityDefinition $definition, string $permission): void
    {
        $acl = $definition->getAcl($permission);
        if (null !== $acl && !$this->securityFacade->isGranted($acl)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function denyUnlessActionAllowed(CustomEntityDefinition $definition, string $action): void
    {
        if (!$definition->isActionEnabled($action)) {
            throw new NotFoundHttpException(sprintf('The "%s" action is disabled for "%s".', $action, $definition->getName()));
        }
        $this->denyUnlessGranted($definition, $action);
    }

    private function decodeContent(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON body.', $exception);
        }
        if (!is_array($data)) {
            throw new BadRequestHttpException('The JSON body must be an object.');
        }

        return $data;
    }

    private function normalizeViolations(ConstraintViolationListInterface $violations, AbstractCustomEntity $entity): array
    {
        $normalized = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $normalized[] = $this->violationNormalizer->normalize($violation, 'internal_api', ['entity' => $entity]);
        }

        return $normalized;
    }

    private function propertyExceptionToViolation(PropertyException $exception): array
    {
        return [
            'path' => $exception->getPropertyName(),
            'attribute' => $exception->getPropertyName(),
            'message' => $exception->getMessage(),
            'global' => false,
        ];
    }
}
