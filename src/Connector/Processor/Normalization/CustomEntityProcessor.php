<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\Processor\Normalization;

use Akeneo\Channel\Infrastructure\Component\Repository\LocaleRepositoryInterface;
use Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes custom entities to flat rows for CSV exports. Translatable
 * entities get one "label-<locale>" column per activated locale, so that
 * every row of a file has the same columns.
 */
class CustomEntityProcessor implements ItemProcessorInterface, StepExecutionAwareInterface
{
    private ?array $locales = null;

    public function __construct(
        private readonly NormalizerInterface $flatNormalizer,
        private readonly LocaleRepositoryInterface $localeRepository,
        private readonly ObjectDetacherInterface $detacher,
    ) {
    }

    public function process($item)
    {
        $context = [];
        if ($item instanceof AbstractTranslatableCustomEntity) {
            $this->locales ??= $this->localeRepository->getActivatedLocaleCodes();
            $context['locales'] = $this->locales;
        }

        $normalized = $this->flatNormalizer->normalize($item, 'flat', $context);
        $this->detacher->detach($item);

        return $normalized;
    }

    /**
     * Resets the locale cache between job executions.
     */
    public function setStepExecution(StepExecution $stepExecution): void
    {
        $this->locales = null;
    }
}
