<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Registry;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Fabric;
use DanMyers\CustomEntityBundle\Exception\UnknownCustomEntityException;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use PHPUnit\Framework\TestCase;

final class CustomEntityRegistryTest extends TestCase
{
    use EntityConfigTrait;

    private CustomEntityRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CustomEntityRegistry(self::normalizedEntities([
            'color' => ['class' => Color::class, 'actions' => ['history' => false]],
            'fabric' => ['class' => Fabric::class, 'reference_data' => 'multi'],
        ]));
    }

    public function test_it_lists_the_configured_entities(): void
    {
        self::assertSame(['color', 'fabric'], $this->registry->getNames());
        self::assertTrue($this->registry->has('color'));
        self::assertFalse($this->registry->has('brand'));
        self::assertCount(2, $this->registry->all());
    }

    public function test_it_exposes_a_definition(): void
    {
        $color = $this->registry->get('color');

        self::assertSame('color', $color->getName());
        self::assertSame(Color::class, $color->getClass());
        self::assertTrue($color->isReferenceData());
        self::assertSame('simple', $color->getReferenceDataType());
        self::assertSame('danmyers_custom_entity_color_delete', $color->getAcl('delete'));
        self::assertNull($color->getAcl('unknown'));
        self::assertFalse($color->isActionEnabled('history'));
        self::assertTrue($color->isActionEnabled('create'));
        self::assertFalse($color->isActionEnabled('unknown'));
        self::assertSame('multi', $this->registry->get('fabric')->getReferenceDataType());
    }

    public function test_it_throws_for_an_unknown_entity(): void
    {
        $this->expectException(UnknownCustomEntityException::class);
        $this->expectExceptionMessage('No custom entity named "brand" is configured.');

        $this->registry->get('brand');
    }

    public function test_it_finds_a_definition_from_an_object_a_class_or_a_subclass(): void
    {
        self::assertSame('color', $this->registry->findByClass(new Color())->getName());
        self::assertSame('fabric', $this->registry->findByClass(Fabric::class)->getName());
        self::assertSame('color', $this->registry->findByClass(new class () extends Color {})->getName());
        self::assertNull($this->registry->findByClass(\stdClass::class));
    }
}
