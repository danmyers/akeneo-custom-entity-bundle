<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Registry;

/**
 * Immutable, fully resolved configuration of one custom entity type.
 */
final class CustomEntityDefinition
{
    /**
     * @param array<string, string> $acl
     * @param array<string, bool>   $actions
     * @param array<string, array>  $fields
     * @param array<string, mixed>  $options
     */
    public function __construct(
        private readonly string $name,
        private readonly string $class,
        private readonly string|false $referenceData,
        private readonly string $label,
        private readonly array $acl,
        private readonly string $manager,
        private readonly string $editForm,
        private readonly string $createForm,
        private readonly string $indexForm,
        private readonly string $datagrid,
        private readonly array $actions,
        private readonly array $fields,
        private readonly array $options,
        private readonly bool $menu = true,
        private readonly int $menuPosition = 100,
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            $config['name'],
            $config['class'],
            $config['reference_data'],
            $config['label'],
            $config['acl'],
            $config['manager'],
            $config['edit_form'],
            $config['create_form'],
            $config['index_form'],
            $config['datagrid'],
            $config['actions'],
            $config['fields'],
            $config['options'],
            $config['menu'] ?? true,
            $config['menu_position'] ?? 100,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return class-string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    public function isReferenceData(): bool
    {
        return false !== $this->referenceData;
    }

    public function getReferenceDataType(): ?string
    {
        return false === $this->referenceData ? null : $this->referenceData;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * ACL resource id for one of: index, create, edit, delete, history.
     */
    public function getAcl(string $permission): ?string
    {
        return $this->acl[$permission] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getAcls(): array
    {
        return $this->acl;
    }

    public function getManagerAlias(): string
    {
        return $this->manager;
    }

    public function getEditForm(): string
    {
        return $this->editForm;
    }

    public function getCreateForm(): string
    {
        return $this->createForm;
    }

    public function getIndexForm(): string
    {
        return $this->indexForm;
    }

    public function getDatagrid(): string
    {
        return $this->datagrid;
    }

    public function isActionEnabled(string $action): bool
    {
        return $this->actions[$action] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Field overrides declared in configuration, keyed by property name.
     *
     * @return array<string, array>
     */
    public function getFieldOverrides(): array
    {
        return $this->fields;
    }

    public function isInMenu(): bool
    {
        return $this->menu;
    }

    public function getMenuPosition(): int
    {
        return $this->menuPosition;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
