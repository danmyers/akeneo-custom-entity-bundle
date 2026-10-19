<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\EventSubscriber;

use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Usage\EntityUsageChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Refuses to remove a custom entity while a product or product model still
 * uses it through a reference data attribute, whatever triggers the removal
 * (UI, mass action, custom code).
 */
class PreventRemovalOfUsedEntitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityUsageChecker $usageChecker)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorageEvents::PRE_REMOVE => 'checkEntityUsage',
            StorageEvents::PRE_REMOVE_ALL => 'checkEntitiesUsage',
        ];
    }

    public function checkEntityUsage(RemoveEvent $event): void
    {
        $subject = $event->getSubject();
        if ($subject instanceof AbstractCustomEntity) {
            $this->usageChecker->assertNotUsed($subject);
        }
    }

    public function checkEntitiesUsage(RemoveEvent $event): void
    {
        $subjects = $event->getSubject();
        if (!is_iterable($subjects)) {
            return;
        }
        foreach ($subjects as $subject) {
            if ($subject instanceof AbstractCustomEntity) {
                $this->usageChecker->assertNotUsed($subject);
            }
        }
    }
}
