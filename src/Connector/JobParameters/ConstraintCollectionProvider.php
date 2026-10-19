<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\JobParameters;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

/**
 * Constraints of the custom entity jobs: those of the core simple CSV
 * import/export, plus the custom entity type to process (and the ids of the
 * entities to export, for the grid quick export).
 */
class ConstraintCollectionProvider implements ConstraintCollectionProviderInterface
{
    /**
     * @param string[] $supportedJobNames
     */
    public function __construct(
        private readonly ConstraintCollectionProviderInterface $baseProvider,
        private readonly CustomEntityRegistry $registry,
        private readonly array $supportedJobNames,
        private readonly bool $withIds = false,
    ) {
    }

    public function getConstraintCollection(): Collection
    {
        $base = $this->baseProvider->getConstraintCollection();

        $fields = $base->fields;
        $fields['custom_entity'] = new Required([
            new NotBlank(['groups' => ['Execution'], 'message' => 'danmyers_custom_entity.job.custom_entity.not_blank']),
            new Choice([
                'choices' => $this->registry->getNames(),
                'message' => 'danmyers_custom_entity.job.custom_entity.invalid',
                'groups' => ['Default', 'Execution'],
            ]),
        ]);

        if ($this->withIds) {
            $fields['ids'] = new Optional([
                new Type(['type' => 'array', 'groups' => ['Default', 'Execution']]),
                new All(['constraints' => [new Type('numeric')], 'groups' => ['Default', 'Execution']]),
            ]);
        }

        // a new collection, so that its validation groups include the ones of
        // the added fields (they are computed at construction time)
        return new Collection([
            'fields' => $fields,
            'allowExtraFields' => $base->allowExtraFields,
            'allowMissingFields' => $base->allowMissingFields,
        ]);
    }

    public function supports(JobInterface $job): bool
    {
        return in_array($job->getName(), $this->supportedJobNames, true);
    }
}
