<?php

declare(strict_types=1);

namespace DanMyers\CustomEntityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Semantic configuration of the bundle:
 *
 *     danmyers_custom_entity:
 *         entities:
 *             color:
 *                 class: App\Entity\Color
 *                 reference_data: simple
 */
final class Configuration implements ConfigurationInterface
{
    public const ACTIONS = ['create', 'edit', 'delete', 'mass_delete', 'quick_export', 'history'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('danmyers_custom_entity');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->useAttributeAsKey('name')
                    ->validate()
                        ->ifTrue(static fn (array $entities): bool => [] !== array_filter(
                            array_keys($entities),
                            static fn (string $name): bool => 1 !== preg_match('/^[a-z][a-z0-9_]*$/', $name),
                        ))
                        ->thenInvalid('Custom entity names must be snake_case (lowercase letters, digits and underscores, starting with a letter). Got %s.')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')
                                ->isRequired()
                                ->cannotBeEmpty()
                                ->info('FQCN of a Doctrine entity extending AbstractCustomEntity.')
                            ->end()
                            ->enumNode('reference_data')
                                ->values(['simple', 'multi', false])
                                ->defaultValue('simple')
                                ->info('Register the entity as Akeneo reference data usable by "simple" or "multi" select attributes; false to skip.')
                            ->end()
                            ->scalarNode('label')
                                ->defaultNull()
                                ->info('Translation key or plain text used in the menu, page titles and job forms. Defaults to a humanised name.')
                            ->end()
                            ->scalarNode('acl_prefix')
                                ->defaultNull()
                                ->info('Prefix of the generated ACL resources (<prefix>_index, _create, _edit, _delete, _history). Defaults to danmyers_custom_entity_<name>.')
                            ->end()
                            ->scalarNode('manager')
                                ->defaultValue('default')
                                ->info('Alias of a service tagged "danmyers_custom_entity.manager".')
                            ->end()
                            ->booleanNode('menu')->defaultTrue()->info('Show the entity in the "Custom entities" menu.')->end()
                            ->integerNode('menu_position')->defaultValue(100)->info('Position in the menu; entities with the same position are sorted by label.')->end()
                            ->scalarNode('index_form')
                                ->defaultNull()
                                ->info('Root of a custom index form extension. Null uses the generic one (danmyers-custom-entity-index).')
                            ->end()
                            ->scalarNode('create_form')
                                ->defaultNull()
                                ->info('Root of a custom creation modal form extension. Null uses the generic one (danmyers-custom-entity-create-modal).')
                            ->end()
                            ->scalarNode('edit_form')
                                ->defaultNull()
                                ->info('Root of a custom edit form extension. Null uses the generic one (danmyers-custom-entity-edit-form).')
                            ->end()
                            ->scalarNode('datagrid')
                                ->defaultNull()
                                ->info('Name of a custom datagrid. Null uses the generated one.')
                            ->end()
                            ->arrayNode('actions')
                                ->info('Enable or disable actions; all are enabled by default.')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('create')->defaultTrue()->end()
                                    ->booleanNode('edit')->defaultTrue()->end()
                                    ->booleanNode('delete')->defaultTrue()->end()
                                    ->booleanNode('mass_delete')->defaultTrue()->end()
                                    ->booleanNode('quick_export')->defaultTrue()->end()
                                    ->booleanNode('history')->defaultTrue()->end()
                                ->end()
                            ->end()
                            ->arrayNode('fields')
                                ->info('Optional per-field overrides of what is inferred from Doctrine metadata.')
                                ->useAttributeAsKey('name')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('label')->defaultNull()->end()
                                        ->enumNode('type')
                                            ->values([null, 'text', 'textarea', 'number', 'boolean', 'date', 'datetime', 'select', 'multiselect', 'image'])
                                            ->defaultNull()
                                        ->end()
                                        ->booleanNode('form')->defaultNull()->info('Show the field in the edit form.')->end()
                                        ->booleanNode('grid')->defaultNull()->info('Show the field as a datagrid column.')->end()
                                        ->booleanNode('filter')->defaultNull()->info('Add a datagrid filter for the field.')->end()
                                        ->booleanNode('required')->defaultNull()->end()
                                        ->integerNode('position')->defaultNull()->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('options')
                                ->info('Free-form options, available to custom managers, listeners and form extensions.')
                                ->variablePrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
