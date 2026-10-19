<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Datagrid;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use DanMyers\CustomEntityBundle\Datagrid\DatagridConfigurationProvider;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldDescriptor;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use Oro\Bundle\DataGridBundle\Provider\SystemAwareResolver;
use PHPUnit\Framework\TestCase;

final class DatagridConfigurationProviderTest extends TestCase
{
    use EntityConfigTrait;

    private function provider(array $entities, array $fields, bool $translatable = false): DatagridConfigurationProvider
    {
        $schema = $this->createMock(FieldSchemaProvider::class);
        $schema->method('getFields')->willReturn($fields);
        $schema->method('isTranslatable')->willReturn($translatable);

        $resolver = $this->createMock(SystemAwareResolver::class);
        $resolver->method('resolve')->willReturnArgument(1);

        $normalized = self::normalizedEntities($entities);

        return new DatagridConfigurationProvider(new CustomEntityRegistry($normalized), $schema, $resolver, $normalized);
    }

    private static function field(string $name, string $type, bool $grid = true, bool $filter = false, ?string $target = null): FieldDescriptor
    {
        return new FieldDescriptor($name, $type, ucfirst($name), false, true, $grid, $filter, 10, $target, null);
    }

    public function test_it_only_serves_generated_grids(): void
    {
        $provider = $this->provider([
            'color' => ['class' => Color::class],
            'custom' => ['class' => Color::class, 'datagrid' => 'my-own-grid'],
        ], []);

        self::assertTrue($provider->isApplicable('danmyers-custom-entity-color-grid'));
        self::assertFalse($provider->isApplicable('my-own-grid'));
        self::assertFalse($provider->isApplicable('product-grid'));
    }

    public function test_it_builds_columns_filters_sorters_and_actions_from_the_schema(): void
    {
        $provider = $this->provider(['color' => ['class' => Color::class]], [
            'name' => self::field('name', FieldDescriptor::TYPE_TEXT, true, true),
            'metallic' => self::field('metallic', FieldDescriptor::TYPE_BOOLEAN),
            'fabric' => self::field('fabric', FieldDescriptor::TYPE_SELECT, true, false, 'App\\Fabric'),
            'tags' => self::field('tags', FieldDescriptor::TYPE_MULTISELECT),
            'notes' => self::field('notes', FieldDescriptor::TYPE_TEXTAREA, false),
        ]);

        $config = $provider->getConfiguration('danmyers-custom-entity-color-grid')->toArray();

        self::assertSame('pim_datasource_default', $config['source']['type']);
        self::assertSame(Color::class, $config['source']['entity']);
        self::assertSame(['custom_entity' => 'color'], $config['source']['repository_parameters']);
        self::assertSame('danmyers_custom_entity_color_index', $config['source']['acl_resource']);

        self::assertSame(['code', 'name', 'metallic', 'fabric'], array_keys($config['columns']), 'collections and non grid fields are not columns');
        self::assertSame('boolean', $config['columns']['metallic']['frontend_type']);
        self::assertSame('assoc_fabric.code', $config['sorters']['columns']['fabric']['data_name']);
        self::assertSame(['code', 'name'], array_keys($config['filters']['columns']));
        self::assertSame('rd.name', $config['filters']['columns']['name']['data_name']);

        self::assertSame(['edit', 'delete'], array_keys($config['actions']));
        self::assertSame('danmyers_custom_entity_color_delete', $config['actions']['delete']['acl_resource']);
        self::assertSame(['delete', 'quick_export_csv', 'quick_export_xlsx'], array_keys($config['mass_actions']));
        self::assertSame(DatagridConfigurationProvider::MASS_DELETE_HANDLER, $config['mass_actions']['delete']['handler']);
        self::assertSame('color', $config['mass_actions']['quick_export_xlsx']['route_parameters']['customEntityName']);
        self::assertSame(['customEntityName', 'id'], $config['properties']['edit_link']['params']);
    }

    public function test_translatable_entities_get_a_label_column_in_the_grid_locale(): void
    {
        $config = $this->provider(['pictogram' => ['class' => Color::class]], [], true)
            ->getConfiguration('danmyers-custom-entity-pictogram-grid')
            ->toArray();

        self::assertSame('localeCode', $config['options']['locale_parameter']);
        self::assertArrayHasKey('label', $config['columns']);
        self::assertSame('translation.label', $config['filters']['columns']['label']['data_name']);
    }

    public function test_disabled_actions_are_not_generated(): void
    {
        $config = $this->provider(['color' => ['class' => Color::class, 'actions' => ['delete' => false, 'quick_export' => false]]], [])
            ->getConfiguration('danmyers-custom-entity-color-grid')
            ->toArray();

        self::assertSame(['edit'], array_keys($config['actions']));
        self::assertArrayNotHasKey('mass_actions', $config, 'mass delete needs the delete action');
    }
}
