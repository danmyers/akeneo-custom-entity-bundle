<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Entity;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use DanMyers\CustomEntityBundle\Entity\CodeGeneratingInterface;
use DanMyers\CustomEntityBundle\Entity\CodeGenerator;
use PHPUnit\Framework\TestCase;

final class CodeGeneratorTest extends TestCase
{
    /**
     * @dataProvider rawCodes
     */
    public function test_it_normalizes_free_text_to_a_code(string $raw, string $expected): void
    {
        self::assertSame($expected, CodeGenerator::normalize($raw));
    }

    public static function rawCodes(): array
    {
        return [
            'words' => ['2026 Jeep Wrangler Unlimited JL', '2026_jeep_wrangler_unlimited_jl'],
            'punctuation' => ['  Mercedes-Benz / G-Class (W463)  ', 'mercedes_benz_g_class_w463'],
            'accents' => ['Citroën Déesse', 'citroen_deesse'],
            'empty parts' => ['2024 Ford F-150 ', '2024_ford_f_150'],
            'nothing usable' => ['***', ''],
        ];
    }

    public function test_it_never_exceeds_the_maximum_length(): void
    {
        $code = CodeGenerator::normalize(str_repeat('abc ', 40));

        self::assertLessThanOrEqual(CodeGenerator::MAX_LENGTH, strlen($code));
        self::assertStringEndsNotWith('_', $code);
    }

    public function test_it_only_sets_the_code_of_generating_entities_without_code(): void
    {
        $entity = new class () extends AbstractCustomEntity implements CodeGeneratingInterface {
            public function generateCode(): string
            {
                return '2026 Jeep Wrangler Unlimited JL';
            }
        };

        CodeGenerator::apply($entity);
        self::assertSame('2026_jeep_wrangler_unlimited_jl', $entity->getCode());

        $entity->setCode('kept');
        CodeGenerator::apply($entity);
        self::assertSame('kept', $entity->getCode(), 'an existing code is never replaced');

        $color = new Color();
        CodeGenerator::apply($color);
        self::assertNull($color->getCode(), 'other entities are left alone');
    }
}
