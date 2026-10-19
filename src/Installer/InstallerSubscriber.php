<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Installer;

use Akeneo\Platform\Bundle\InstallerBundle\Event\InstallerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Creates the bundle job instances when the PIM is installed
 * ("bin/console pim:installer:db").
 */
class InstallerSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly JobInstanceInstaller $installer)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [InstallerEvents::POST_LOAD_FIXTURES => 'installJobInstances'];
    }

    public function installJobInstances(): void
    {
        $this->installer->install();
    }
}
