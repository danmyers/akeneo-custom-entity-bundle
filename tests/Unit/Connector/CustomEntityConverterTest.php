<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Connector;

use Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException;
use DanMyers\CustomEntityBundle\Connector\ArrayConverter\FlatToStandard\CustomEntityConverter;
use PHPUnit\Framework\TestCase;

final class CustomEntityConverterTest extends TestCase
{
    public function test_it_converts_label_columns_and_keeps_the_others(): void
    {
        $converted = (new CustomEntityConverter())->convert([
            'code' => 'wash',
            'label-en_US' => 'Wash',
            'label-fr_FR' => 'Laver',
            'colors' => 'red,blue',
            'sortOrder' => '3',
        ]);

        self::assertSame([
            'code' => 'wash',
            'labels' => ['en_US' => 'Wash', 'fr_FR' => 'Laver'],
            'colors' => 'red,blue',
            'sortOrder' => '3',
        ], $converted);
    }

    public function test_the_code_column_is_mandatory(): void
    {
        $this->expectException(DataArrayConversionException::class);

        (new CustomEntityConverter())->convert(['name' => 'x']);
    }

    public function test_an_empty_code_is_passed_on_for_entities_generating_it(): void
    {
        self::assertSame(['code' => '', 'year' => '2026'], (new CustomEntityConverter())->convert(['code' => '', 'year' => '2026']));
    }
}
