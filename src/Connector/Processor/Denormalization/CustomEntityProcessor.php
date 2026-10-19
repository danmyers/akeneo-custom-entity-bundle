<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Connector\Processor\Denormalization;

use Akeneo\Tool\Component\Batch\Item\FileInvalidItem;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\Connector\Exception\InvalidItemFromViolationsException;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\PropertyException;
use DanMyers\CustomEntityBundle\Entity\CodeGeneratingInterface;
use DanMyers\CustomEntityBundle\Manager\ManagerRegistry;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;

/**
 * Creates or updates the custom entity matching each imported row (by code),
 * then validates it. Invalid rows are skipped and reported.
 *
 * For entities implementing CodeGeneratingInterface the code column may be
 * left empty: the code is generated from the row and matched as usual.
 */
class CustomEntityProcessor implements ItemProcessorInterface, StepExecutionAwareInterface
{
    private ?StepExecution $stepExecution = null;

    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ObjectDetacherInterface $detacher,
    ) {
    }

    public function process($item)
    {
        $definition = $this->registry->get((string) $this->stepExecution->getJobParameters()->get('custom_entity'));
        $manager = $this->managerRegistry->getForDefinition($definition);

        $row = $item;
        $position = $this->stepExecution->getSummaryInfo('item_position');
        $code = trim((string) ($item['code'] ?? ''));
        unset($item['code']);

        try {
            if ('' === $code && is_a($definition->getClass(), CodeGeneratingInterface::class, true)) {
                // no code in the file: generate it from the row, then update
                // the entity that already has this code, if any
                $entity = $manager->create($definition, $item);
                $existing = $manager->findByCode($definition, (string) $entity->getCode());
                $isNew = null === $existing;
                if (!$isNew) {
                    $entity = $existing;
                    $manager->update($entity, $item);
                }
            } else {
                $entity = '' === $code ? null : $manager->findByCode($definition, $code);
                $isNew = null === $entity;
                if ($isNew) {
                    $entity = $manager->create($definition);
                    $item = ['code' => $code] + $item;
                }
                $manager->update($entity, $item);
            }
        } catch (PropertyException $exception) {
            $this->stepExecution->incrementSummaryInfo('skip');
            if (isset($entity, $isNew) && !$isNew) {
                $this->detacher->detach($entity);
            }
            throw new InvalidItemException($exception->getMessage(), new FileInvalidItem($row, $position), [], 0, $exception);
        }

        $violations = $manager->validate($entity);
        if (0 < $violations->count()) {
            $this->skip($entity, $isNew);
            throw new InvalidItemFromViolationsException($violations, new FileInvalidItem($row, $position));
        }

        return $entity;
    }

    public function setStepExecution(StepExecution $stepExecution): void
    {
        $this->stepExecution = $stepExecution;
    }

    private function skip(object $entity, bool $isNew): void
    {
        $this->stepExecution->incrementSummaryInfo('skip');
        if (!$isNew) {
            $this->detacher->detach($entity);
        }
    }
}
