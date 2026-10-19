<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Normalizer;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Brand;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Fabric;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Pictogram;
use DanMyers\CustomEntityBundle\Normalizer\Flat\CustomEntityNormalizer as FlatNormalizer;
use DanMyers\CustomEntityBundle\Normalizer\Standard\CustomEntityNormalizer as StandardNormalizer;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Schema\FieldDescriptor;
use DanMyers\CustomEntityBundle\Schema\FieldSchemaProvider;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

final class CustomEntityNormalizersTest extends TestCase
{
    use EntityConfigTrait;

    private CustomEntityRegistry $registry;
    private FieldSchemaProvider $schema;

    protected function setUp(): void
    {
        $this->registry = new CustomEntityRegistry(self::normalizedEntities([
            'color' => ['class' => Color::class],
            'fabric' => ['class' => Fabric::class],
            'brand' => ['class' => Brand::class],
            'pictogram' => ['class' => Pictogram::class],
        ]));

        $field = static fn (string $name, string $type) => new FieldDescriptor($name, $type, $name, false, true, true, false, 10);
        $fields = [
            Color::class => ['name' => $field('name', 'text'), 'metallic' => $field('metallic', 'boolean'), 'sortOrder' => $field('sortOrder', 'number')],
            Fabric::class => ['name' => $field('name', 'text'), 'colors' => $field('colors', 'multiselect'), 'weightGsm' => $field('weightGsm', 'number')],
            Brand::class => ['signatureFabric' => $field('signatureFabric', 'select'), 'foundedOn' => $field('foundedOn', 'date'), 'logo' => $field('logo', 'image')],
            Pictogram::class => ['iconName' => $field('iconName', 'text')],
        ];

        $this->schema = $this->createMock(FieldSchemaProvider::class);
        $this->schema->method('getFields')->willReturnCallback(static fn ($definition) => $fields[$definition->getClass()]);
    }

    private function standard(): StandardNormalizer
    {
        return new StandardNormalizer($this->registry, $this->schema, PropertyAccess::createPropertyAccessor());
    }

    private function flat(): FlatNormalizer
    {
        return new FlatNormalizer($this->registry, $this->schema, PropertyAccess::createPropertyAccessor());
    }

    private function fabric(): Fabric
    {
        $red = (new Color())->setCode('red')->setName('Red');
        $blue = (new Color())->setCode('blue')->setName('Blue')->setMetallic(true);

        return (new Fabric())->setCode('denim')->setName('Denim')->addColor($red)->addColor($blue)->setWeightGsm(340);
    }

    public function test_the_standard_format_uses_codes_for_associations(): void
    {
        self::assertSame(
            ['code' => 'denim', 'sortOrder' => 1, 'name' => 'Denim', 'colors' => ['red', 'blue'], 'weightGsm' => 340],
            $this->standard()->normalize($this->fabric(), 'standard'),
        );

        $brand = (new Brand())->setCode('acme')->setSignatureFabric($this->fabric())->setFoundedOn(new \DateTime('1999-12-31'));
        self::assertSame(
            ['code' => 'acme', 'sortOrder' => 1, 'signatureFabric' => 'denim', 'foundedOn' => '1999-12-31', 'logo' => null],
            $this->standard()->normalize($brand, 'standard'),
        );
    }

    public function test_the_standard_format_includes_labels_of_translatable_entities(): void
    {
        $pictogram = (new Pictogram())->setCode('wash')->setIconName('tub');
        $pictogram->setLabel('Wash', 'en_US');

        self::assertSame(
            ['code' => 'wash', 'sortOrder' => 1, 'labels' => ['en_US' => 'Wash'], 'iconName' => 'tub'],
            $this->standard()->normalize($pictogram, 'standard'),
        );
    }

    public function test_the_flat_format_is_made_of_strings(): void
    {
        self::assertSame(
            ['code' => 'denim', 'name' => 'Denim', 'colors' => 'red,blue', 'weightGsm' => '340', 'sortOrder' => '1'],
            $this->flat()->normalize($this->fabric(), 'flat'),
        );
        self::assertSame(
            ['code' => 'blue', 'name' => 'Blue', 'metallic' => '1', 'sortOrder' => '1'],
            $this->flat()->normalize((new Color())->setCode('blue')->setName('Blue')->setMetallic(true), 'flat'),
        );
    }

    public function test_the_flat_format_has_one_column_per_requested_locale(): void
    {
        $pictogram = (new Pictogram())->setCode('wash');
        $pictogram->setLabel('Laver', 'fr_FR');

        self::assertSame(
            ['code' => 'wash', 'label-fr_FR' => 'Laver', 'iconName' => '', 'sortOrder' => '1'],
            $this->flat()->normalize($pictogram, 'flat'),
            'history snapshots only list the existing labels',
        );
        self::assertSame(
            ['code' => 'wash', 'label-en_US' => '', 'label-fr_FR' => 'Laver', 'iconName' => '', 'sortOrder' => '1'],
            $this->flat()->normalize($pictogram, 'flat', ['locales' => ['en_US', 'fr_FR']]),
            'exports list every activated locale',
        );
    }

    public function test_they_only_support_custom_entities_in_their_format(): void
    {
        self::assertTrue($this->standard()->supportsNormalization(new Color(), 'standard'));
        self::assertFalse($this->standard()->supportsNormalization(new Color(), 'flat'));
        self::assertFalse($this->standard()->supportsNormalization(new \stdClass(), 'standard'));
        self::assertTrue($this->flat()->supportsNormalization(new Color(), 'flat'));
        self::assertTrue($this->flat()->supportsNormalization(new Color(), 'csv'));
        self::assertFalse($this->flat()->supportsNormalization(new Color(), 'standard'));
    }
}
