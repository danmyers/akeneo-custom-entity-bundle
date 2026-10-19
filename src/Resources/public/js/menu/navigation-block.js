'use strict';

/**
 * Navigation block listing one menu item per custom entity type the user
 * can see. Items are added at configuration time from the types endpoint,
 * so no form extension has to be declared per custom entity.
 */
define(['underscore', 'oro/translator', 'pim/menu/navigation-block', 'pim/menu/item', 'danmyers-custom-entity/type-registry'], function (
  _,
  __,
  NavigationBlock,
  Item,
  TypeRegistry
) {
  return NavigationBlock.extend({
    /**
     * {@inheritdoc}
     */
    configure: function () {
      return TypeRegistry.fetchAll().then(types => {
        const inMenu = _.values(types).filter(type => type.menu);
        // by position, then by label
        _.sortBy(_.sortBy(inMenu, type => __(type.label).toLowerCase()), 'menuPosition').forEach((type, index) => {
          const item = new Item({
            config: {
              title: type.label,
              to: 'danmyers_custom_entity_index',
              routeParams: {customEntityName: type.name},
            },
          });
          this.addExtension(type.menuItem, item, 'self', (index + 1) * 10);
        });

        return NavigationBlock.prototype.configure.apply(this, arguments);
      });
    },
  });
});
