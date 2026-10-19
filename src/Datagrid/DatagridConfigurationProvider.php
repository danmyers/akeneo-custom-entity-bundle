<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Datagrid;

use DanMyers\CustomEntityBundle\Registry\CustomEntityDefinition;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldDescriptor;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Provider\ConfigurationProviderInterface;
use Oro\Bundle\DataGridBundle\Provider\SystemAwareResolver;

/**
 * Generates the datagrid of every custom entity that does not declare its
 * own: code, label and the fields flagged "grid" as columns, row edit and
 * delete actions, filters, sorters, mass delete and quick exports.
 */
class DatagridConfigurationProvider implements ConfigurationProviderInterface
{
    public const MASS_DELETE_HANDLER = 'danmyers_custom_entity_mass_delete';
    public const QUICK_EXPORT_HANDLER = 'danmyers_custom_entity_quick_export';

    /** @var array<string, string> grid name => custom entity name */
    private array $grids = [];

    /** @var array<string, array> */
    private array $configurations = [];

    /**
     * @param array<string, array> $entities normalized bundle configuration
     */
    public function __construct(
        private readonly CustomEntityRegistry $registry,
        private readonly FieldSchemaProvider $schemaProvider,
        private readonly SystemAwareResolver $resolver,
        array $entities,
    ) {
        foreach ($entities as $name => $entity) {
            if ($entity['generate']['datagrid']) {
                $this->grids[$entity['datagrid']] = $name;
            }
        }
    }

    public function isApplicable($gridName)
    {
        return isset($this->grids[$gridName]);
    }

    public function getConfiguration($gridName)
    {
        if (!isset($this->grids[$gridName])) {
            throw new \RuntimeException(sprintf('A configuration for "%s" datagrid was not found.', $gridName));
        }

        $this->configurations[$gridName] ??= $this->resolver->resolve(
            $gridName,
            $this->buildConfiguration($this->registry->get($this->grids[$gridName])),
        );

        return DatagridConfiguration::createNamed($gridName, $this->configurations[$gridName]);
    }

    /**
     * Raw configuration, in the same structure as a datagrid YAML file. Public
     * so that it can be dumped as a starting point for a custom grid.
     */
    public function buildConfiguration(CustomEntityDefinition $definition): array
    {
        $name = $definition->getName();
        $translatable = $this->schemaProvider->isTranslatable($definition);
        $fields = array_filter(
            $this->schemaProvider->getFields($definition),
            static fn (FieldDescriptor $field): bool => $field->grid || $field->filter,
        );

        $config = [
            'options' => [
                // Akeneo builds the row delete messages from this hint
                // ("pim_enrich.entity.custom_entity.*", see jsmessages)
                'entityHint' => 'custom_entity',
                'manageFilters' => false,
            ],
            'source' => [
                'acl_resource' => $definition->getAcl('index'),
                'type' => 'pim_datasource_default',
                'entity' => $definition->getClass(),
                'repository_method' => 'createDatagridQueryBuilder',
                'repository_parameters' => ['custom_entity' => $name],
            ],
            'columns' => [
                'code' => ['label' => 'pim_common.code'],
            ],
            'properties' => [
                'id' => null,
                'edit_link' => [
                    'type' => 'url',
                    'route' => 'danmyers_custom_entity_edit',
                    'params' => ['customEntityName', 'id'],
                ],
                'delete_link' => [
                    'type' => 'url',
                    'route' => 'danmyers_custom_entity_rest_delete',
                    'params' => ['customEntityName', 'id'],
                ],
            ],
            'actions' => [
                'edit' => [
                    'launcherOptions' => ['className' => 'AknIconButton AknIconButton--small AknIconButton--edit'],
                    'type' => 'navigate',
                    'label' => 'pim_common.edit',
                    'link' => 'edit_link',
                    'rowAction' => true,
                    'acl_resource' => $definition->getAcl('index'),
                ],
            ],
            'sorters' => [
                'columns' => [
                    'code' => ['data_name' => 'rd.code'],
                ],
                'default' => ['code' => 'ASC'],
            ],
            'filters' => [
                'columns' => [
                    'code' => ['type' => 'string', 'label' => 'pim_common.code', 'data_name' => 'rd.code'],
                ],
            ],
            'mass_actions' => [],
        ];

        if ($translatable) {
            $config['options']['locale_parameter'] = 'localeCode';
            $config['columns']['label'] = ['label' => 'pim_common.label', 'frontend_type' => 'label'];
            $config['sorters']['columns']['label'] = ['data_name' => 'label'];
            $config['filters']['columns']['label'] = ['type' => 'search', 'label' => 'pim_common.label', 'data_name' => 'translation.label'];
        }

        foreach ($fields as $field) {
            $dataName = $field->isAssociation() ? sprintf('assoc_%s.code', $field->name) : sprintf('rd.%s', $field->name);

            if ($field->grid && !$field->isCollection() && FieldDescriptor::TYPE_IMAGE !== $field->type) {
                $config['columns'][$field->name] = array_filter([
                    'label' => $field->label,
                    'frontend_type' => self::frontendType($field),
                ]);
                $config['sorters']['columns'][$field->name] = ['data_name' => $dataName];
            }

            if ($field->filter && null !== $filterType = self::filterType($field)) {
                $config['filters']['columns'][$field->name] = [
                    'type' => $filterType,
                    'label' => $field->label,
                    'data_name' => $dataName,
                ];
            }
        }

        if ($definition->isActionEnabled('delete')) {
            $config['actions']['delete'] = [
                'launcherOptions' => ['className' => 'AknIconButton AknIconButton--small AknIconButton--trash'],
                'type' => 'delete',
                'label' => 'pim_common.delete',
                'link' => 'delete_link',
                'acl_resource' => $definition->getAcl('delete'),
            ];
        }

        if ($definition->isActionEnabled('mass_delete') && $definition->isActionEnabled('delete')) {
            $config['mass_actions']['delete'] = [
                'type' => 'delete',
                'label' => 'pim_common.delete',
                'entity_name' => $name,
                'custom_entity' => $name,
                'acl_resource' => $definition->getAcl('delete'),
                'handler' => self::MASS_DELETE_HANDLER,
                'className' => 'AknButton AknButton--important AknButtonList-item',
                'messages' => [
                    'confirm_title' => 'pim_common.confirm_deletion',
                    'confirm_content' => 'danmyers_custom_entity.mass_action.delete.confirm_content',
                    'confirm_ok' => 'pim_common.ok',
                    'success' => 'danmyers_custom_entity.mass_action.delete.success',
                    'error' => 'danmyers_custom_entity.mass_action.delete.error',
                    'empty_selection' => 'danmyers_custom_entity.mass_action.delete.empty_selection',
                ],
            ];
        }

        if ($definition->isActionEnabled('quick_export')) {
            foreach (['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'] as $format => $contentType) {
                $config['mass_actions']['quick_export_' . $format] = [
                    'type' => 'export',
                    'label' => 'danmyers_custom_entity.mass_action.quick_export.' . $format,
                    'custom_entity' => $name,
                    'acl_resource' => $definition->getAcl('index'),
                    'handler' => self::QUICK_EXPORT_HANDLER,
                    'route' => 'danmyers_custom_entity_quick_export',
                    'route_parameters' => [
                        'customEntityName' => $name,
                        '_format' => $format,
                        '_contentType' => $contentType,
                    ],
                    'className' => 'AknButton AknButton--action AknButtonList-item',
                    'context' => ['withHeader' => true],
                    'messages' => [
                        'empty_selection' => 'danmyers_custom_entity.mass_action.delete.empty_selection',
                    ],
                ];
            }
        }

        if ([] === $config['mass_actions']) {
            unset($config['mass_actions']);
        }

        return $config;
    }

    private static function frontendType(FieldDescriptor $field): ?string
    {
        return match ($field->type) {
            FieldDescriptor::TYPE_BOOLEAN => 'boolean',
            FieldDescriptor::TYPE_DATE => 'date',
            FieldDescriptor::TYPE_DATETIME => 'datetime',
            default => null,
        };
    }

    private static function filterType(FieldDescriptor $field): ?string
    {
        return match ($field->type) {
            FieldDescriptor::TYPE_TEXT, FieldDescriptor::TYPE_TEXTAREA, FieldDescriptor::TYPE_SELECT => 'string',
            FieldDescriptor::TYPE_NUMBER => 'number',
            FieldDescriptor::TYPE_BOOLEAN => 'boolean',
            FieldDescriptor::TYPE_DATE => 'date',
            FieldDescriptor::TYPE_DATETIME => 'datetime',
            default => null,
        };
    }
}
