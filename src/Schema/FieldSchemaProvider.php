<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Schema;

use Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Infers the editable fields of a custom entity from its Doctrine metadata,
 * then applies the per-field overrides of the bundle configuration.
 */
class FieldSchemaProvider
{
    /** Properties managed by the bundle itself and never listed as fields. */
    public const RESERVED_FIELDS = ['id', 'code', 'created', 'updated', 'translations', 'locale'];

    /** @var array<string, FieldDescriptor[]> */
    private array $cache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomEntityRegistry $registry,
    ) {
    }

    /**
     * @return array<string, FieldDescriptor> keyed by property name, sorted by position
     */
    public function getFields(CustomEntityDefinition $definition): array
    {
        return $this->cache[$definition->getName()] ??= $this->buildFields($definition);
    }

    public function getField(CustomEntityDefinition $definition, string $name): ?FieldDescriptor
    {
        return $this->getFields($definition)[$name] ?? null;
    }

    public function isTranslatable(CustomEntityDefinition $definition): bool
    {
        return is_a($definition->getClass(), AbstractTranslatableCustomEntity::class, true);
    }

    /**
     * @return array<string, FieldDescriptor>
     */
    private function buildFields(CustomEntityDefinition $definition): array
    {
        $metadata = $this->entityManager->getClassMetadata($definition->getClass());
        $overrides = $definition->getFieldOverrides();
        $labelProperty = $definition->getClass()::getLabelProperty();

        $fields = [];
        $position = 10;

        foreach ($metadata->getFieldNames() as $name) {
            if (in_array($name, self::RESERVED_FIELDS, true)) {
                continue;
            }

            $mapping = $metadata->getFieldMapping($name);
            $type = $this->guessScalarType($mapping['type'], $mapping['length'] ?? null);
            if (null === $type) {
                continue;
            }

            $isSortOrder = 'sortOrder' === $name;
            // a non-nullable column without default must be filled, except
            // booleans for which an unchecked box is a valid value
            $required = !($mapping['nullable'] ?? false)
                && !isset($mapping['options']['default'])
                && FieldDescriptor::TYPE_BOOLEAN !== $type;
            $fields[$name] = [
                'type' => $type,
                'required' => $required && !$isSortOrder,
                'form' => true,
                'grid' => !$isSortOrder && in_array($type, [FieldDescriptor::TYPE_TEXT, FieldDescriptor::TYPE_NUMBER, FieldDescriptor::TYPE_BOOLEAN, FieldDescriptor::TYPE_DATE], true),
                'filter' => $name === $labelProperty,
                'position' => $isSortOrder ? 1000 : $position += 10,
                'maxLength' => FieldDescriptor::TYPE_TEXT === $type ? ($mapping['length'] ?? 255) : null,
            ];
        }

        foreach ($metadata->getAssociationMappings() as $name => $mapping) {
            if (in_array($name, self::RESERVED_FIELDS, true)) {
                continue;
            }

            $target = $mapping['targetEntity'];
            $isToOne = (bool) ($mapping['type'] & ClassMetadataInfo::TO_ONE);

            if (is_a($target, FileInfoInterface::class, true)) {
                if ($isToOne) {
                    $fields[$name] = ['type' => FieldDescriptor::TYPE_IMAGE, 'required' => false, 'form' => true, 'grid' => false, 'filter' => false, 'position' => $position += 10, 'target' => $target];
                }
                continue;
            }

            $targetDefinition = $this->registry->findByClass($target);
            $targetMetadata = $this->entityManager->getClassMetadata($target);
            if (null === $targetDefinition && !$targetMetadata->hasField('code')) {
                continue;
            }
            if (!$isToOne && !$mapping['isOwningSide'] && ClassMetadataInfo::MANY_TO_MANY !== $mapping['type']) {
                // one-to-many collections are owned by the other side
                continue;
            }

            $joinColumns = $mapping['joinColumns'] ?? [];
            $nullable = [] === $joinColumns || ($joinColumns[0]['nullable'] ?? true);

            $fields[$name] = [
                'type' => $isToOne ? FieldDescriptor::TYPE_SELECT : FieldDescriptor::TYPE_MULTISELECT,
                'required' => $isToOne && !$nullable,
                'form' => true,
                'grid' => $isToOne,
                'filter' => false,
                'position' => $position += 10,
                'target' => $target,
                'targetCustomEntity' => $targetDefinition?->getName(),
            ];
        }

        $descriptors = [];
        foreach ($fields as $name => $field) {
            $override = array_filter($overrides[$name] ?? [], static fn ($value): bool => null !== $value);
            $field = array_merge($field, $override);

            $descriptors[$name] = new FieldDescriptor(
                $name,
                $field['type'],
                $field['label'] ?? self::humanize($name),
                (bool) $field['required'],
                (bool) $field['form'],
                (bool) $field['grid'],
                (bool) $field['filter'],
                (int) $field['position'],
                $field['target'] ?? null,
                $field['targetCustomEntity'] ?? null,
                $field['maxLength'] ?? null,
            );
        }

        uasort($descriptors, static fn (FieldDescriptor $a, FieldDescriptor $b): int => $a->position <=> $b->position);

        return $descriptors;
    }

    private function guessScalarType(string $doctrineType, ?int $length): ?string
    {
        return match ($doctrineType) {
            'string', 'ascii_string', 'guid' => null !== $length && $length > 255 ? FieldDescriptor::TYPE_TEXTAREA : FieldDescriptor::TYPE_TEXT,
            'text' => FieldDescriptor::TYPE_TEXTAREA,
            'integer', 'smallint', 'bigint', 'decimal', 'float' => FieldDescriptor::TYPE_NUMBER,
            'boolean' => FieldDescriptor::TYPE_BOOLEAN,
            'date', 'date_immutable' => FieldDescriptor::TYPE_DATE,
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => FieldDescriptor::TYPE_DATETIME,
            default => null,
        };
    }

    public static function humanize(string $property): string
    {
        $words = preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $property));

        return ucfirst(strtolower((string) $words));
    }
}
