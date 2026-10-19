<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Normalizer\InternalApi;

use Akeneo\Tool\Bundle\VersioningBundle\Manager\VersionManager;
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
 * Normalizes custom entities for the UI: the standard format plus a "meta"
 * section used by the edit form (entity type, form to render, field schema,
 * history information).
 */
class CustomEntityNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(
        private readonly NormalizerInterface $standardNormalizer,
        private readonly CustomEntityRegistry $registry,
        private readonly FieldSchemaProvider $schemaProvider,
        private readonly VersionManager $versionManager,
        private readonly NormalizerInterface $versionNormalizer,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function normalize($entity, $format = null, array $context = []): array
    {
        /** @var AbstractCustomEntity $entity */
        $definition = $this->registry->findByClass($entity);
        $normalized = $this->standardNormalizer->normalize($entity, 'standard', $context);

        // the media field of the UI works with {filePath, originalFilename}
        if (null !== $definition) {
            foreach ($this->schemaProvider->getFields($definition) as $field) {
                if (FieldDescriptor::TYPE_IMAGE === $field->type) {
                    $file = $this->propertyAccessor->getValue($entity, $field->name);
                    $normalized[$field->name] = [
                        'filePath' => $file instanceof FileInfoInterface ? $file->getKey() : null,
                        'originalFilename' => $file instanceof FileInfoInterface ? $file->getOriginalFilename() : null,
                    ];
                }
            }
        }

        $oldestVersion = $this->versionManager->getOldestLogEntry($entity);
        $newestVersion = $this->versionManager->getNewestLogEntry($entity);

        $normalized['meta'] = [
            'id' => $entity->getId(),
            'customEntityName' => $definition?->getName(),
            'model_type' => $definition?->getName(),
            'form' => $definition?->getEditForm(),
            'label' => $this->getLabel($entity, $context),
            'translatable' => null !== $definition && $this->schemaProvider->isTranslatable($definition),
            'fields' => null === $definition ? [] : array_values(array_map(
                static fn ($field): array => $field->toArray(),
                $this->schemaProvider->getFields($definition),
            )),
            'created' => null !== $oldestVersion ? $this->versionNormalizer->normalize($oldestVersion, 'internal_api', $context) : null,
            'updated' => null !== $newestVersion ? $this->versionNormalizer->normalize($newestVersion, 'internal_api', $context) : null,
            'structure_version' => null !== $entity->getUpdated() ? $entity->getUpdated()->getTimestamp() : null,
        ];

        return $normalized;
    }

    /**
     * Label in the catalog locale given by the "catalogLocale" (or "locale")
     * context key, falling back to "[code]".
     */
    private function getLabel(AbstractCustomEntity $entity, array $context): string
    {
        if ($entity instanceof AbstractTranslatableCustomEntity) {
            $label = $entity->getLabel($context['catalogLocale'] ?? $context['locale'] ?? null);

            return null !== $label && '' !== $label ? $label : sprintf('[%s]', $entity->getCode());
        }

        return (string) $entity;
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof AbstractCustomEntity && 'internal_api' === $format;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
