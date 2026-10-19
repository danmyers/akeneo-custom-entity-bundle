<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Updater;

use Akeneo\Channel\Infrastructure\Component\Repository\LocaleRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\FileStorage;
use Akeneo\Tool\Component\FileStorage\File\FileStorerInterface;
use Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface;
use Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Tool\Component\StorageUtils\Exception\UnknownPropertyException;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\AbstractTranslatableCustomEntity;
use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldDescriptor;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface as PropertyAccessException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Updates a custom entity from standard format data:
 *
 *     [
 *         'code' => 'red',
 *         'sortOrder' => 10,
 *         'labels' => ['en_US' => 'Red', 'fr_FR' => 'Rouge'],   // translatable entities
 *         'hex' => '#ff0000',                                   // scalar fields
 *         'fabric' => 'cotton',                                 // single association, by code
 *         'colors' => ['red', 'blue'],                          // collection association, by codes
 *         'picture' => 'a/b/c/picture.png',                     // file, by key or local path
 *     ]
 *
 * Keys may be camelCase property names or their snake_case equivalent.
 */
class CustomEntityUpdater implements ObjectUpdaterInterface
{
    private const IGNORED_KEYS = ['id', 'meta', 'created', 'updated'];

    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly FieldSchemaProvider $schemaProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly LocaleRepositoryInterface $localeRepository,
        private readonly FileInfoRepositoryInterface $fileInfoRepository,
        private readonly FileStorerInterface $fileStorer,
    ) {
    }

    public function update($entity, array $data, array $options = []): self
    {
        if (!$entity instanceof AbstractCustomEntity) {
            throw InvalidObjectException::objectExpected(get_debug_type($entity), AbstractCustomEntity::class);
        }

        $definition = $this->registry->findByClass($entity);
        if (null === $definition) {
            throw new \InvalidArgumentException(sprintf('The class "%s" is not a configured custom entity.', ClassUtils::getClass($entity)));
        }

        foreach ($data as $key => $value) {
            if (in_array($key, self::IGNORED_KEYS, true)) {
                continue;
            }
            $this->updateProperty($definition, $entity, self::camelize((string) $key), $value);
        }

        return $this;
    }

    private function updateProperty(CustomEntityDefinition $definition, AbstractCustomEntity $entity, string $property, mixed $value): void
    {
        $class = ClassUtils::getClass($entity);

        switch ($property) {
            case 'code':
                if (!is_scalar($value) || '' === trim((string) $value)) {
                    throw InvalidPropertyException::valueNotEmptyExpected('code', $class);
                }
                if (null !== $entity->getId() && $entity->getCode() !== (string) $value) {
                    throw InvalidPropertyException::expected('The code of an existing entity cannot be changed.', $class);
                }
                $entity->setCode((string) $value);

                return;
            case 'sortOrder':
                if (null === $value || '' === $value) {
                    return;
                }
                if (!is_numeric($value)) {
                    throw InvalidPropertyTypeException::integerExpected('sortOrder', $class, $value);
                }
                $entity->setSortOrder((int) $value);

                return;
            case 'labels':
                $this->updateLabels($entity, $property, $value);

                return;
            case 'label':
                if ($entity instanceof AbstractTranslatableCustomEntity) {
                    $this->updateLabels($entity, $property, $value);

                    return;
                }
                break;
        }

        $field = $this->schemaProvider->getField($definition, $property);
        if (null === $field) {
            $this->setRawValue($entity, $property, $value);

            return;
        }

        // a mapped property without setter is computed by the entity (e.g. a
        // label built from other fields): it is exported, never imported
        if (!$this->propertyAccessor->isWritable($entity, $property)) {
            return;
        }

        $this->propertyAccessor->setValue($entity, $property, $this->convertValue($field, $class, $value));
    }

    private function updateLabels(AbstractCustomEntity $entity, string $property, mixed $value): void
    {
        $class = ClassUtils::getClass($entity);

        if (!$entity instanceof AbstractTranslatableCustomEntity) {
            throw UnknownPropertyException::unknownProperty($property);
        }

        if (!is_array($value)) {
            throw InvalidPropertyTypeException::arrayExpected($property, $class, $value);
        }

        $activatedLocales = $this->localeRepository->getActivatedLocaleCodes();
        foreach ($value as $locale => $label) {
            if (!in_array($locale, $activatedLocales, true)) {
                throw InvalidPropertyException::validEntityCodeExpected(
                    'labels',
                    'locale',
                    sprintf('the locale "%s" is not activated', $locale),
                    $class,
                    $locale,
                );
            }
            if (null !== $label && !is_scalar($label)) {
                throw InvalidPropertyTypeException::scalarExpected('labels', $class, $label);
            }

            $label = null === $label ? '' : trim((string) $label);
            if ('' === $label) {
                $translation = $entity->findTranslation($locale);
                if (null !== $translation) {
                    $entity->removeTranslation($translation);
                }
                continue;
            }

            $entity->getTranslation($locale)->setLabel($label);
        }
    }

    private function convertValue(FieldDescriptor $field, string $class, mixed $value): mixed
    {
        if (FieldDescriptor::TYPE_MULTISELECT !== $field->type && (null === $value || '' === $value)) {
            // an empty boolean of a non-nullable column means "unchecked"
            if (FieldDescriptor::TYPE_BOOLEAN === $field->type && !$this->isNullable($field, $class)) {
                return false;
            }

            return null;
        }

        return match ($field->type) {
            FieldDescriptor::TYPE_TEXT, FieldDescriptor::TYPE_TEXTAREA => $this->toString($field, $class, $value),
            FieldDescriptor::TYPE_NUMBER => $this->toNumber($field, $class, $value),
            FieldDescriptor::TYPE_BOOLEAN => $this->toBoolean($field, $class, $value),
            FieldDescriptor::TYPE_DATE, FieldDescriptor::TYPE_DATETIME => $this->toDate($field, $class, $value),
            FieldDescriptor::TYPE_SELECT => $this->findByCode($field, $class, $value),
            FieldDescriptor::TYPE_MULTISELECT => $this->findAllByCodes($field, $class, $value),
            FieldDescriptor::TYPE_IMAGE => $this->toFile($field, $class, $value),
            default => $value,
        };
    }

    private function isNullable(FieldDescriptor $field, string $class): bool
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        return !$metadata->hasField($field->name) || $metadata->isNullable($field->name);
    }

    private function toString(FieldDescriptor $field, string $class, mixed $value): string
    {
        if (!is_scalar($value)) {
            throw InvalidPropertyTypeException::stringExpected($field->name, $class, $value);
        }

        return (string) $value;
    }

    private function toNumber(FieldDescriptor $field, string $class, mixed $value): int|float|string
    {
        if (!is_numeric($value)) {
            throw InvalidPropertyTypeException::numericExpected($field->name, $class, $value);
        }

        $type = $this->entityManager->getClassMetadata($class)->getTypeOfField($field->name);

        return match ($type) {
            'integer', 'smallint' => (int) $value,
            'float' => (float) $value,
            default => (string) $value, // decimal and bigint are strings in Doctrine
        };
    }

    private function toBoolean(FieldDescriptor $field, string $class, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = is_string($value) ? strtolower(trim($value)) : $value;

        return match ($normalized) {
            1, '1', 'true', 'yes', 'on' => true,
            0, '0', 'false', 'no', 'off' => false,
            default => throw InvalidPropertyTypeException::booleanExpected($field->name, $class, $value),
        };
    }

    private function toDate(FieldDescriptor $field, string $class, mixed $value): \DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if (!is_string($value)) {
            throw InvalidPropertyException::dateExpected($field->name, 'yyyy-mm-dd', $class, is_scalar($value) ? (string) $value : get_debug_type($value));
        }

        try {
            $date = new \DateTime($value);
        } catch (\Exception) {
            throw InvalidPropertyException::dateExpected($field->name, 'yyyy-mm-dd', $class, $value);
        }

        $type = $this->entityManager->getClassMetadata($class)->getTypeOfField($field->name);

        return str_ends_with((string) $type, '_immutable') ? \DateTimeImmutable::createFromMutable($date) : $date;
    }

    private function findByCode(FieldDescriptor $field, string $class, mixed $code): object
    {
        if (is_array($code) && isset($code['code'])) {
            $code = $code['code'];
        }
        if (!is_scalar($code)) {
            throw InvalidPropertyTypeException::scalarExpected($field->name, $class, $code);
        }

        /** @var class-string $target */
        $target = $field->targetEntity;
        $related = $this->entityManager->getRepository($target)->findOneBy(['code' => (string) $code]);
        if (null === $related) {
            throw InvalidPropertyException::validEntityCodeExpected(
                $field->name,
                'code',
                'the entity does not exist',
                $class,
                (string) $code,
            );
        }

        return $related;
    }

    /**
     * @return object[]
     */
    private function findAllByCodes(FieldDescriptor $field, string $class, mixed $codes): array
    {
        if (null === $codes || '' === $codes) {
            return [];
        }
        if (is_string($codes)) {
            $codes = array_map('trim', explode(',', $codes));
        }
        if (!is_array($codes)) {
            throw InvalidPropertyTypeException::arrayExpected($field->name, $class, $codes);
        }

        $codes = array_values(array_unique(array_filter(
            array_map(static fn ($code) => is_array($code) ? ($code['code'] ?? null) : $code, $codes),
            static fn ($code): bool => null !== $code && '' !== $code,
        )));

        return array_map(fn ($code): object => $this->findByCode($field, $class, $code), $codes);
    }

    private function toFile(FieldDescriptor $field, string $class, mixed $value): ?FileInfoInterface
    {
        if ($value instanceof FileInfoInterface) {
            return $value;
        }

        $path = is_array($value) ? ($value['filePath'] ?? $value['key'] ?? null) : $value;
        if (null === $path || '' === $path) {
            // the media field sends {"filePath": null} when cleared
            return null;
        }
        if (!is_string($path)) {
            throw InvalidPropertyException::validPathExpected($field->name, $class, is_scalar($path) ? (string) $path : '');
        }

        $existing = $this->fileInfoRepository->findOneByIdentifier($path);
        if ($existing instanceof FileInfoInterface) {
            return $existing;
        }

        $localFile = new \SplFileInfo($path);
        if (!$localFile->isFile()) {
            throw InvalidPropertyException::validPathExpected($field->name, $class, $path);
        }

        return $this->fileStorer->store($localFile, FileStorage::CATALOG_STORAGE_ALIAS);
    }

    /**
     * Properties that are not inferred fields (custom setters, json columns,
     * ...) are written as-is through the property accessor.
     */
    private function setRawValue(AbstractCustomEntity $entity, string $property, mixed $value): void
    {
        if (!$this->propertyAccessor->isWritable($entity, $property)) {
            throw UnknownPropertyException::unknownProperty($property);
        }

        try {
            $this->propertyAccessor->setValue($entity, $property, $value);
        } catch (PropertyAccessException $exception) {
            $previous = $exception instanceof \Exception ? $exception : new \RuntimeException($exception->getMessage());
            throw InvalidPropertyException::expectedFromPreviousException($property, ClassUtils::getClass($entity), $previous);
        }
    }

    public static function camelize(string $key): string
    {
        if (!str_contains($key, '_')) {
            return $key;
        }

        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    }
}
