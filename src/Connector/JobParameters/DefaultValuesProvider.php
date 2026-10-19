<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\JobParameters;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;

/**
 * Default values of the custom entity jobs: those of the core simple CSV
 * import/export plus an empty custom entity type.
 */
class DefaultValuesProvider implements DefaultValuesProviderInterface
{
    /**
     * @param string[] $supportedJobNames
     */
    public function __construct(
        private readonly DefaultValuesProviderInterface $baseProvider,
        private readonly array $supportedJobNames,
        private readonly bool $withIds = false,
        private readonly ?string $filePath = null,
    ) {
    }

    public function getDefaultValues(): array
    {
        $values = $this->baseProvider->getDefaultValues();
        $values['custom_entity'] = null;

        if ($this->withIds) {
            $values['ids'] = null;
        }

        if (null !== $this->filePath && isset($values['storage'])) {
            $values['storage']['file_path'] = $this->filePath;
        }

        return $values;
    }

    public function supports(JobInterface $job): bool
    {
        return in_array($job->getName(), $this->supportedJobNames, true);
    }
}
