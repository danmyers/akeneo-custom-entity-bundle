<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class DanMyersCustomEntityExtension extends Extension
{
    public const ENTITIES_PARAMETER = 'danmyers_custom_entity.entities';

    public function getAlias(): string
    {
        return 'danmyers_custom_entity';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $entities = [];
        foreach ($config['entities'] as $name => $entity) {
            $entities[$name] = self::normalizeEntity($name, $entity);
        }

        $container->setParameter(self::ENTITIES_PARAMETER, $entities);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        foreach ([
            'services.yml',
            'controllers.yml',
            'normalizers.yml',
            'datagrid.yml',
            'connectors.yml',
            'jobs.yml',
        ] as $file) {
            $loader->load($file);
        }
    }

    /**
     * Fills in every derived default so that the rest of the bundle (compiler
     * passes, generators, runtime registry) works on one complete structure.
     */
    public static function normalizeEntity(string $name, array $entity): array
    {
        $aclPrefix = $entity['acl_prefix'] ?? sprintf('danmyers_custom_entity_%s', $name);
        $slug = str_replace('_', '-', $name);

        return [
            'name' => $name,
            'class' => ltrim($entity['class'], '\\'),
            'reference_data' => $entity['reference_data'],
            'label' => $entity['label'] ?? ucfirst(str_replace('_', ' ', $name)),
            'acl_prefix' => $aclPrefix,
            'acl' => [
                'index' => $aclPrefix . '_index',
                'create' => $aclPrefix . '_create',
                'edit' => $aclPrefix . '_edit',
                'delete' => $aclPrefix . '_delete',
                'history' => $aclPrefix . '_history',
            ],
            'manager' => $entity['manager'],
            'menu' => $entity['menu'],
            'menu_position' => $entity['menu_position'],
            // generic form trees shared by every custom entity (form_extensions/*.yml)
            'index_form' => $entity['index_form'] ?? 'danmyers-custom-entity-index',
            'create_form' => $entity['create_form'] ?? 'danmyers-custom-entity-create-modal',
            'edit_form' => $entity['edit_form'] ?? 'danmyers-custom-entity-edit-form',
            'datagrid' => $entity['datagrid'] ?? sprintf('danmyers-custom-entity-%s-grid', $slug),
            'generate' => [
                'datagrid' => null === $entity['datagrid'],
            ],
            'menu_item' => sprintf('danmyers-custom-entity-menu-%s', $slug),
            'actions' => $entity['actions'],
            'fields' => $entity['fields'] ?? [],
            'options' => $entity['options'] ?? [],
        ];
    }
}
