<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\Tests\Unit\DependencyInjection;

use Acme\Bundle\CustomEntityDemoBundle\Entity\Color;
use Acme\Bundle\CustomEntityDemoBundle\Entity\Fabric;
use DanMyers\CustomEntityBundle\Controller\AjaxOptionController;
use DanMyers\CustomEntityBundle\DependencyInjection\Compiler\RegisterCustomEntitiesPass;
use DanMyers\CustomEntityBundle\DependencyInjection\DanMyersCustomEntityExtension;
use DanMyers\CustomEntityBundle\Tests\Unit\EntityConfigTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class RegisterCustomEntitiesPassTest extends TestCase
{
    use EntityConfigTrait;

    private function container(array $entities, array $declaredReferenceData = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter(DanMyersCustomEntityExtension::ENTITIES_PARAMETER, self::normalizedEntities($entities));
        $container->setParameter('pim_reference_data.configurations', $declaredReferenceData);
        $container->setDefinition('pim_reference_data.registry', new Definition(\stdClass::class));
        $container->setDefinition('pim_ui.controller.ajax_option', new Definition(\stdClass::class));

        return $container;
    }

    public function test_it_registers_reference_data_history_classes_and_the_option_controller(): void
    {
        $container = $this->container([
            'color' => ['class' => Color::class],
            'fabric' => ['class' => Fabric::class, 'reference_data' => 'multi'],
            'internal' => ['class' => Color::class, 'reference_data' => false],
        ]);

        (new RegisterCustomEntitiesPass())->process($container);

        self::assertSame(Color::class, $container->getParameter('pim_catalog.entity.color.class'));
        self::assertSame(Fabric::class, $container->getParameter('pim_catalog.entity.fabric.class'));
        self::assertSame(
            [
                ['registerRaw', [['class' => Color::class, 'type' => 'simple'], 'color']],
                ['registerRaw', [['class' => Fabric::class, 'type' => 'multi'], 'fabric']],
            ],
            $container->getDefinition('pim_reference_data.registry')->getMethodCalls(),
        );
        self::assertSame(AjaxOptionController::class, $container->getDefinition('pim_ui.controller.ajax_option')->getClass());
    }

    public function test_reference_data_already_declared_with_the_same_class_is_not_registered_twice(): void
    {
        $container = $this->container(['color' => ['class' => Color::class]], ['color' => ['class' => '\\' . Color::class, 'type' => 'simple']]);

        (new RegisterCustomEntitiesPass())->process($container);

        self::assertSame([], $container->getDefinition('pim_reference_data.registry')->getMethodCalls());
    }

    public function test_it_refuses_a_reference_data_name_used_by_another_class(): void
    {
        $container = $this->container(['color' => ['class' => Color::class]], ['color' => ['class' => 'Acme\\Other\\Color', 'type' => 'simple']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already used by "Acme\\Other\\Color"');

        (new RegisterCustomEntitiesPass())->process($container);
    }

    public function test_it_refuses_a_class_that_is_not_a_custom_entity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must extend');

        (new RegisterCustomEntitiesPass())->process($this->container(['thing' => ['class' => \ArrayObject::class]]));
    }

    public function test_it_refuses_a_name_used_by_an_akeneo_entity_type(): void
    {
        $container = $this->container(['attribute' => ['class' => Color::class]]);
        $container->setParameter('pim_catalog.entity.attribute.class', 'Akeneo\\Attribute');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with an Akeneo entity type');

        (new RegisterCustomEntitiesPass())->process($container);
    }
}
