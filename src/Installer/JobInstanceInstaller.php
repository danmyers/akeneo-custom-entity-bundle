<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Installer;

use Akeneo\Tool\Component\Batch\Job\JobParametersFactory;
use Akeneo\Tool\Component\Batch\Job\JobRegistry;
use Akeneo\Tool\Component\Batch\Model\JobInstance;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;

/**
 * Creates the job instances the bundle relies on (the grid quick exports).
 * Import and export profiles are created by users from the UI, like any
 * other Akeneo job.
 */
class JobInstanceInstaller
{
    public const CONNECTOR = 'Custom Entity Connector';
    public const CSV_IMPORT = 'danmyers_custom_entity_csv_import';
    public const CSV_EXPORT = 'danmyers_custom_entity_csv_export';
    public const XLSX_IMPORT = 'danmyers_custom_entity_xlsx_import';
    public const XLSX_EXPORT = 'danmyers_custom_entity_xlsx_export';
    public const CSV_QUICK_EXPORT = 'danmyers_custom_entity_csv_quick_export';
    public const XLSX_QUICK_EXPORT = 'danmyers_custom_entity_xlsx_quick_export';

    private const QUICK_EXPORTS = [
        'csv' => [self::CSV_QUICK_EXPORT, 'Custom entity CSV quick export'],
        'xlsx' => [self::XLSX_QUICK_EXPORT, 'Custom entity XLSX quick export'],
    ];

    public function __construct(
        private readonly IdentifiableObjectRepositoryInterface $jobInstanceRepository,
        private readonly SaverInterface $jobInstanceSaver,
        private readonly JobRegistry $jobRegistry,
        private readonly JobParametersFactory $jobParametersFactory,
    ) {
    }

    /**
     * @return string[] codes of the job instances created
     */
    public function install(): array
    {
        $created = [];
        foreach (array_keys(self::QUICK_EXPORTS) as $format) {
            [$code] = self::QUICK_EXPORTS[$format];
            if (null === $this->jobInstanceRepository->findOneByIdentifier($code)) {
                $this->getOrCreateQuickExport($format);
                $created[] = $code;
            }
        }

        return $created;
    }

    public function getOrCreateQuickExport(string $format): JobInstance
    {
        if (!isset(self::QUICK_EXPORTS[$format])) {
            throw new \InvalidArgumentException(sprintf('Unsupported quick export format "%s".', $format));
        }

        [$code, $label] = self::QUICK_EXPORTS[$format];
        $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($code);
        if ($jobInstance instanceof JobInstance) {
            return $jobInstance;
        }

        $jobInstance = new JobInstance(self::CONNECTOR, 'quick_export', $code);
        $jobInstance->setCode($code);
        $jobInstance->setLabel($label);

        $job = $this->jobRegistry->get($code);
        $rawParameters = $this->jobParametersFactory->create($job)->all();
        $rawParameters['storage'] = [
            'type' => 'none',
            'file_path' => sprintf('%s/custom_entity_%%custom_entity%%_%%datetime%%.%s', sys_get_temp_dir(), $format),
        ];
        $jobInstance->setRawParameters($rawParameters);

        $this->jobInstanceSaver->save($jobInstance);

        return $jobInstance;
    }
}
