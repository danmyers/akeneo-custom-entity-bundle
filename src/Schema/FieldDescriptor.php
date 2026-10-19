<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Schema;

/**
 * Describes one editable property of a custom entity, as inferred from
 * Doctrine metadata and the bundle configuration.
 */
final class FieldDescriptor
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';
    public const TYPE_IMAGE = 'image';

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required,
        public readonly bool $form,
        public readonly bool $grid,
        public readonly bool $filter,
        public readonly int $position,
        public readonly ?string $targetEntity = null,
        public readonly ?string $targetCustomEntity = null,
        public readonly ?int $maxLength = null,
    ) {
    }

    public function isAssociation(): bool
    {
        return null !== $this->targetEntity;
    }

    public function isCollection(): bool
    {
        return self::TYPE_MULTISELECT === $this->type;
    }

    /**
     * Shape exposed to the frontend in the "meta.fields" of the REST API.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'form' => $this->form,
            'grid' => $this->grid,
            'filter' => $this->filter,
            'position' => $this->position,
            'customEntity' => $this->targetCustomEntity,
            'maxLength' => $this->maxLength,
        ];
    }
}
