<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\Connector;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;
use DanMyers\CustomEntityBundle\Connector\JobParameters\ConstraintCollectionProvider;
use DanMyers\CustomEntityBundle\Connector\JobParameters\DefaultValuesProvider;
use DanMyers\CustomEntityBundle\Registry\CustomEntityRegistry;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

final class JobParametersTest extends TestCase
{
    use EntityConfigTrait;

    private function constraints(bool $withIds = false): ConstraintCollectionProvider
    {
        $base = $this->createMock(ConstraintCollectionProviderInterface::class);
        $base->method('getConstraintCollection')->willReturn(new Collection([
            'fields' => ['delimiter' => new NotBlank(['groups' => ['Default', 'FileConfiguration']])],
        ]));

        return new ConstraintCollectionProvider(
            $base,
            new CustomEntityRegistry(self::normalizedEntities(['color' => ['class' => Color::class]])),
            ['my_job'],
            $withIds,
        );
    }

    public function test_a_custom_entity_is_required_at_execution_only(): void
    {
        $collection = $this->constraints()->getConstraintCollection();
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate(['delimiter' => ';', 'custom_entity' => null], $collection, ['Default']));
        self::assertCount(1, $validator->validate(['delimiter' => ';', 'custom_entity' => null], $collection, ['Default', 'Execution']));
        self::assertCount(0, $validator->validate(['delimiter' => ';', 'custom_entity' => 'color'], $collection, ['Default', 'Execution']));
        self::assertCount(1, $validator->validate(['delimiter' => ';', 'custom_entity' => 'brand'], $collection, ['Default']));
    }

    public function test_the_quick_export_accepts_ids(): void
    {
        $collection = $this->constraints(true)->getConstraintCollection();
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate(['delimiter' => ';', 'custom_entity' => 'color', 'ids' => [1, '2']], $collection, ['Default', 'Execution']));
        self::assertGreaterThan(0, count($validator->validate(['delimiter' => ';', 'custom_entity' => 'color', 'ids' => 'all'], $collection, ['Default', 'Execution'])));
    }

    public function test_it_supports_the_configured_jobs(): void
    {
        $job = $this->createMock(JobInterface::class);
        $job->method('getName')->willReturnOnConsecutiveCalls('my_job', 'other_job');

        $provider = $this->constraints();
        self::assertTrue($provider->supports($job));
        self::assertFalse($provider->supports($job));
    }

    public function test_it_adds_default_values(): void
    {
        $base = $this->createMock(DefaultValuesProviderInterface::class);
        $base->method('getDefaultValues')->willReturn(['delimiter' => ';', 'storage' => ['type' => 'none', 'file_path' => '/tmp/a.csv']]);

        self::assertSame(
            ['delimiter' => ';', 'storage' => ['type' => 'none', 'file_path' => '/tmp/a.csv'], 'custom_entity' => null],
            (new DefaultValuesProvider($base, ['my_job']))->getDefaultValues(),
        );
        self::assertSame(
            ['delimiter' => ';', 'storage' => ['type' => 'none', 'file_path' => '/tmp/b.csv'], 'custom_entity' => null, 'ids' => null],
            (new DefaultValuesProvider($base, ['my_job'], true, '/tmp/b.csv'))->getDefaultValues(),
        );
    }
}
