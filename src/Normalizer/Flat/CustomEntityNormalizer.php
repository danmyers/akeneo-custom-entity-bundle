<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Normalizer\Flat;

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
 * Flat (one level, string values) normalizer of custom entities, used for
 * history snapshots and CSV exports:
 *
 *     ['code' => 'red', 'label-en_US' => 'Red', 'hex' => '#f00', 'colors' => 'red,blue', 'sortOrder' => '1']
 *
 * The CSV import reads this format back.
 */
class CustomEntityNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const LABEL_PREFIX = 'label-';

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

        $normalized = ['code' => (string) $entity->getCode()];

        if ($entity instanceof AbstractTranslatableCustomEntity) {
            foreach ($this->getLocales($entity, $context) as $locale) {
                $normalized[self::LABEL_PREFIX . $locale] = (string) $entity->getLabel($locale);
            }
        }

        if (null !== $definition) {
            foreach ($this->schemaProvider->getFields($definition) as $field) {
                if ('sortOrder' === $field->name) {
                    continue;
                }
                $normalized[$field->name] = $this->normalizeValue($field, $this->propertyAccessor->getValue($entity, $field->name));
            }
        }

        $normalized['sortOrder'] = (string) $entity->getSortOrder();

        return $normalized;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof AbstractCustomEntity && in_array($format, ['flat', 'csv'], true);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * Exports pass the activated locales in the "locales" context key so that
     * every row has the same columns; history snapshots only list the
     * locales that have a label.
     *
     * @return string[]
     */
    private function getLocales(AbstractTranslatableCustomEntity $entity, array $context): array
    {
        if (isset($context['locales']) && is_array($context['locales'])) {
            return $context['locales'];
        }

        return array_keys($entity->getLabels());
    }

    private function normalizeValue(FieldDescriptor $field, mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return match ($field->type) {
            FieldDescriptor::TYPE_BOOLEAN => $value ? '1' : '0',
            FieldDescriptor::TYPE_DATE => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value,
            FieldDescriptor::TYPE_DATETIME => $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : (string) $value,
            FieldDescriptor::TYPE_SELECT => method_exists($value, 'getCode') ? (string) $value->getCode() : (string) $value,
            FieldDescriptor::TYPE_MULTISELECT => implode(',', array_map(
                static fn ($item): string => method_exists($item, 'getCode') ? (string) $item->getCode() : (string) $item,
                is_iterable($value) ? iterator_to_array($value, false) : [],
            )),
            FieldDescriptor::TYPE_IMAGE => $value instanceof FileInfoInterface ? (string) $value->getKey() : '',
            default => is_scalar($value) ? (string) $value : '',
        };
    }
}
