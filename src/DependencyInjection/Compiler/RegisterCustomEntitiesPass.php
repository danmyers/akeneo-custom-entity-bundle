<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\DependencyInjection\Compiler;

use DanMyers\CustomEntityBundle\Controller\AjaxOptionController;
use DanMyers\CustomEntityBundle\DependencyInjection\DanMyersCustomEntityExtension;
use DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Wires the configured custom entities into Akeneo:
 *
 * - checks every configured class extends AbstractCustomEntity;
 * - registers the entities flagged as reference data in Akeneo's reference
 *   data registry, so that they can back simple/multi select attributes;
 * - declares "pim_catalog.entity.<name>.class" so that Akeneo's history tab
 *   can resolve the entity type;
 * - swaps Akeneo's option controller for one that forwards the catalog
 *   locale to reference data searches.
 */
final class RegisterCustomEntitiesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $entities = $container->getParameter(DanMyersCustomEntityExtension::ENTITIES_PARAMETER);
        $referenceDataRegistry = $container->hasDefinition('pim_reference_data.registry')
            ? $container->getDefinition('pim_reference_data.registry')
            : null;
        $declaredReferenceData = $container->hasParameter('pim_reference_data.configurations')
            ? (array) $container->getParameter('pim_reference_data.configurations')
            : [];

        foreach ($entities as $name => $entity) {
            $class = $entity['class'];
            if (!class_exists($class)) {
                throw new InvalidArgumentException(sprintf('The class "%s" of the custom entity "%s" does not exist.', $class, $name));
            }
            if (!is_a($class, AbstractCustomEntity::class, true)) {
                throw new InvalidArgumentException(sprintf('The class "%s" of the custom entity "%s" must extend %s.', $class, $name, AbstractCustomEntity::class));
            }

            $parameter = sprintf('pim_catalog.entity.%s.class', $name);
            if ($container->hasParameter($parameter) && $container->getParameter($parameter) !== $class) {
                throw new InvalidArgumentException(sprintf(
                    'The custom entity name "%s" conflicts with an Akeneo entity type (parameter "%s"). Choose another name.',
                    $name,
                    $parameter,
                ));
            }
            $container->setParameter($parameter, $class);

            if (false === $entity['reference_data'] || null === $referenceDataRegistry) {
                continue;
            }
            if (isset($declaredReferenceData[$name])) {
                // already declared in akeneo_pim_structure.reference_data: fine
                // if it is the same class, a silent mix-up otherwise
                if (ltrim($declaredReferenceData[$name]['class'] ?? '', '\\') !== $class) {
                    throw new InvalidArgumentException(sprintf(
                        'The custom entity "%s" cannot be registered as reference data: the reference data name "%s" is already used by "%s" (akeneo_pim_structure.reference_data). Rename the custom entity or set "reference_data: false".',
                        $name,
                        $name,
                        $declaredReferenceData[$name]['class'] ?? '?',
                    ));
                }
                continue;
            }
            $referenceDataRegistry->addMethodCall('registerRaw', [
                ['class' => $class, 'type' => $entity['reference_data']],
                $name,
            ]);
        }

        if ($container->hasDefinition('pim_ui.controller.ajax_option')) {
            $container->getDefinition('pim_ui.controller.ajax_option')->setClass(AjaxOptionController::class);
        }
    }
}
