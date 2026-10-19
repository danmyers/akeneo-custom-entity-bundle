<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Event;

use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched around every REST action of the custom entity API.
 *
 * - CustomEntityEvents::PRE_ACTION: setting a response short-circuits the
 *   action (e.g. extra permission checks, feature flags).
 * - CustomEntityEvents::POST_ACTION: the response can be replaced or
 *   decorated.
 */
final class CustomEntityActionEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly string $action,
        private readonly CustomEntityDefinition $definition,
        private readonly Request $request,
        private readonly ?AbstractCustomEntity $entity = null,
        ?Response $response = null,
    ) {
        $this->response = $response;
    }

    /**
     * One of the CustomEntityEvents::ACTION_* constants.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    public function getDefinition(): CustomEntityDefinition
    {
        return $this->definition;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getEntity(): ?AbstractCustomEntity
    {
        return $this->entity;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }
}
