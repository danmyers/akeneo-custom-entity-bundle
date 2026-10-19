<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Exception;

/**
 * Thrown when removing a custom entity that is still used by products or
 * product models.
 */
final class NonRemovableEntityException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $entityCode = '',
        private readonly int $usageCount = 0,
        private readonly string $attributeCode = '',
    ) {
        parent::__construct($message);
    }

    public static function usedByProducts(string $entityCode, int $count, string $attributeCode): self
    {
        return new self(
            sprintf(
                'The entity "%s" cannot be removed: it is used by %d product(s) or product model(s) through the attribute "%s".',
                $entityCode,
                $count,
                $attributeCode,
            ),
            $entityCode,
            $count,
            $attributeCode,
        );
    }

    public function getEntityCode(): string
    {
        return $this->entityCode;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }
}
