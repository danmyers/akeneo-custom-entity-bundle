<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Export;

use Akeneo\Tool\Bundle\BatchBundle\Launcher\JobLauncherInterface;
use Akeneo\Tool\Component\Batch\Model\JobExecution;
use DanMyers\CustomEntityBundle\Installer\JobInstanceInstaller;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Launches the CSV or XLSX quick export of a selection of custom entities.
 */
class QuickExportLauncher
{
    public const SUPPORTED_FORMATS = ['csv', 'xlsx'];

    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly JobInstanceInstaller $jobInstanceInstaller,
        private readonly JobLauncherInterface $jobLauncher,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param int[] $ids
     */
    public function launch(string $customEntity, array $ids, string $format = 'csv'): JobExecution
    {
        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported quick export format "%s".', $format));
        }

        $definition = $this->registry->get($customEntity);
        $jobInstance = $this->jobInstanceInstaller->getOrCreateQuickExport($format);
        $user = $this->getUser();

        $configuration = $jobInstance->getRawParameters();
        $configuration['custom_entity'] = $definition->getName();
        $configuration['ids'] = $ids;
        if (isset($configuration['storage']['file_path'])) {
            $configuration['storage']['file_path'] = strtr($configuration['storage']['file_path'], [
                '%custom_entity%' => $definition->getName(),
                '%datetime%' => date('Y-m-d_H-i-s'),
            ]);
        }
        if (null !== $user) {
            $configuration['users_to_notify'][] = $user->getUserIdentifier();
        }

        return $this->jobLauncher->launch($jobInstance, $user, $configuration);
    }

    private function getUser(): ?UserInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
