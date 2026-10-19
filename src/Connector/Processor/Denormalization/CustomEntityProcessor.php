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
use DanMyers\CustomEntityBundle\Manager\ManagerRegistry;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;

/**
 * Creates or updates the custom entity matching each imported row (by code),
 * then validates it. Invalid rows are skipped and reported.
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
        $code = (string) $item['code'];
        $entity = $manager->findByCode($definition, $code);
        $isNew = null === $entity;
        if ($isNew) {
            $entity = $manager->create($definition);
        } else {
            unset($item['code']);
        }

        try {
            $manager->update($entity, $item);
        } catch (PropertyException $exception) {
            $this->skip($entity, $isNew);
            throw new InvalidItemException($exception->getMessage(), new FileInvalidItem($row, $this->stepExecution->getSummaryInfo('item_position')), [], 0, $exception);
        }

        $violations = $manager->validate($entity);
        if (0 < $violations->count()) {
            $this->skip($entity, $isNew);
            throw new InvalidItemFromViolationsException($violations, new FileInvalidItem($row, $this->stepExecution->getSummaryInfo('item_position')));
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
