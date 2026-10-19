<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\DependencyInjection;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ConfigurationTest extends TestCase
{
    use EntityConfigTrait;

    public function test_it_fills_in_the_defaults(): void
    {
        $entity = self::normalizedEntities(['brand_color' => ['class' => '\\' . Color::class]])['brand_color'];

        self::assertSame('brand_color', $entity['name']);
        self::assertSame(Color::class, $entity['class'], 'the leading backslash is removed');
        self::assertSame('simple', $entity['reference_data']);
        self::assertSame('Brand color', $entity['label']);
        self::assertSame('default', $entity['manager']);
        self::assertSame([
            'index' => 'danmyers_custom_entity_brand_color_index',
            'create' => 'danmyers_custom_entity_brand_color_create',
            'edit' => 'danmyers_custom_entity_brand_color_edit',
            'delete' => 'danmyers_custom_entity_brand_color_delete',
            'history' => 'danmyers_custom_entity_brand_color_history',
        ], $entity['acl']);
        self::assertSame('danmyers-custom-entity-index', $entity['index_form']);
        self::assertSame('danmyers-custom-entity-create-modal', $entity['create_form']);
        self::assertSame('danmyers-custom-entity-edit-form', $entity['edit_form']);
        self::assertSame('danmyers-custom-entity-brand-color-grid', $entity['datagrid']);
        self::assertTrue($entity['generate']['datagrid']);
        self::assertSame(array_fill_keys(['create', 'edit', 'delete', 'mass_delete', 'quick_export', 'history'], true), $entity['actions']);
    }

    public function test_it_keeps_the_overrides(): void
    {
        $entity = self::normalizedEntities(['color' => [
            'class' => Color::class,
            'reference_data' => false,
            'label' => 'acme.colors',
            'acl_prefix' => 'acme_color',
            'edit_form' => 'acme-color-edit-form',
            'datagrid' => 'acme-color-grid',
            'actions' => ['delete' => false],
            'fields' => ['hex' => ['label' => 'Hex', 'grid' => false]],
        ]])['color'];

        self::assertFalse($entity['reference_data']);
        self::assertSame('acme.colors', $entity['label']);
        self::assertSame('acme_color_edit', $entity['acl']['edit']);
        self::assertSame('acme-color-edit-form', $entity['edit_form']);
        self::assertSame('acme-color-grid', $entity['datagrid']);
        self::assertFalse($entity['generate']['datagrid']);
        self::assertFalse($entity['actions']['delete']);
        self::assertTrue($entity['actions']['edit']);
        self::assertSame('Hex', $entity['fields']['hex']['label']);
        self::assertFalse($entity['fields']['hex']['grid']);
        self::assertNull($entity['fields']['hex']['filter']);
    }

    /**
     * @dataProvider invalidNames
     */
    public function test_it_rejects_names_that_are_not_snake_case(string $name): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::normalizedEntities([$name => ['class' => Color::class]]);
    }

    public static function invalidNames(): array
    {
        return [['Color'], ['1color'], ['color name']];
    }

    public function test_dashes_in_names_are_normalized_like_any_symfony_config_key(): void
    {
        self::assertSame(['brand_color'], array_keys(self::normalizedEntities(['brand-color' => ['class' => Color::class]])));
    }

    public function test_it_rejects_an_unknown_reference_data_type(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::normalizedEntities(['color' => ['class' => Color::class, 'reference_data' => 'many']]);
    }
}
