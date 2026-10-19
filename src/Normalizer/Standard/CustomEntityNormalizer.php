<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Normalizer\Standard;

use Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldDescriptor;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Generic standard format normalizer of custom entities, driven by Doctrine
 * metadata. The output is accepted back by the updater:
 *
 *     ['code' => 'red', 'sortOrder' => 1, 'labels' => [...], 'hex' => '#f00', 'fabric' => 'cotton', 'colors' => ['red']]
 *
 * Register a normalizer with a higher priority on the
 * "pim_standard_format_serializer.normalizer" tag to customise one entity.
 */
class CustomEntityNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly FieldSchemaProvider $schemaProvider,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function normalize($entity, $format = null, array $context = []): array
    {
        /** @var AbstractCustomEntity $entity */
        $definition = $this->registry->findByClass($entity);

        $normalized = [
            'code' => $entity->getCode(),
            'sortOrder' => $entity->getSortOrder(),
        ];

        if ($entity instanceof AbstractTranslatableCustomEntity) {
            $normalized['labels'] = $entity->getLabels();
        }

        if (null !== $definition) {
            foreach ($this->schemaProvider->getFields($definition) as $field) {
                if ('sortOrder' === $field->name) {
                    continue;
                }
                $normalized[$field->name] = $this->normalizeValue($field, $this->propertyAccessor->getValue($entity, $field->name));
            }
        }

        return $normalized;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof AbstractCustomEntity && 'standard' === $format;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    private function normalizeValue(FieldDescriptor $field, mixed $value): mixed
    {
        if (null === $value) {
            return FieldDescriptor::TYPE_MULTISELECT === $field->type ? [] : null;
        }

        return match ($field->type) {
            FieldDescriptor::TYPE_DATE => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            FieldDescriptor::TYPE_DATETIME => $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : $value,
            FieldDescriptor::TYPE_SELECT => method_exists($value, 'getCode') ? $value->getCode() : (string) $value,
            FieldDescriptor::TYPE_MULTISELECT => array_values(array_map(
                static fn ($item) => method_exists($item, 'getCode') ? $item->getCode() : (string) $item,
                is_iterable($value) ? iterator_to_array($value, false) : [],
            )),
            FieldDescriptor::TYPE_IMAGE => $value instanceof FileInfoInterface ? $value->getKey() : null,
            FieldDescriptor::TYPE_NUMBER => is_string($value) && is_numeric($value) ? $value + 0 : $value,
            default => $value,
        };
    }
}
