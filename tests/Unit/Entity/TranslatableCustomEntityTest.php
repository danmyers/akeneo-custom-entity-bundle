<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Entity;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Pictogram;
use Acme\Bundle\CustomEntityDemoBundle\Entity\PictogramTranslation;
use PHPUnit\Framework\TestCase;

final class TranslatableCustomEntityTest extends TestCase
{
    public function test_it_creates_translations_on_demand(): void
    {
        $pictogram = (new Pictogram())->setCode('wash');

        self::assertNull($pictogram->getTranslation(), 'no translation without a locale');

        $translation = $pictogram->getTranslation('en_US');
        self::assertInstanceOf(PictogramTranslation::class, $translation);
        self::assertSame('en_US', $translation->getLocale());
        self::assertSame($pictogram, $translation->getForeignKey());
        self::assertSame($translation, $pictogram->getTranslation('en_US'), 'the same translation is reused');
        self::assertCount(1, $pictogram->getTranslations());
    }

    public function test_it_reads_labels_without_creating_translations(): void
    {
        $pictogram = (new Pictogram())->setCode('wash');
        $pictogram->setLabel('Wash', 'en_US');
        $pictogram->setLabel('Laver', 'fr_FR');

        self::assertSame('Wash', $pictogram->getLabel('en_US'));
        self::assertNull($pictogram->getLabel('de_DE'));
        self::assertCount(2, $pictogram->getTranslations(), 'reading a missing label does not create a translation');
        self::assertSame(['en_US' => 'Wash', 'fr_FR' => 'Laver'], $pictogram->getLabels());
    }

    public function test_its_string_representation_falls_back_to_the_code(): void
    {
        $pictogram = (new Pictogram())->setCode('wash');
        self::assertSame('[wash]', (string) $pictogram);

        $pictogram->setLabel('Laver', 'fr_FR');
        $pictogram->setLocale('fr_FR');
        self::assertSame('Laver', (string) $pictogram);
    }

    public function test_a_simple_entity_uses_its_label_property(): void
    {
        $color = (new Color())->setCode('red');
        self::assertSame('[red]', (string) $color);

        $color->setName('Red');
        self::assertSame('Red', (string) $color);
    }

    public function test_timestamps_are_initialized_on_persist_and_refreshed_on_update(): void
    {
        $color = new Color();
        $color->initializeTimestamps();
        $created = $color->getCreated();

        self::assertNotNull($created);
        self::assertEquals($created, $color->getUpdated());

        $color->refreshUpdatedTimestamp();
        self::assertSame($created, $color->getCreated());
        self::assertGreaterThanOrEqual($created, $color->getUpdated());
    }
}
