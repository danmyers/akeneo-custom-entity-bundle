'use strict';

/**
 * Landing page of the "Custom entities" menu tab. Akeneo's main menu links a
 * tab to a route without parameters, so this controller forwards to the index
 * of the first custom entity type the user can see.
 */
define(['underscore', 'oro/translator', 'pim/controller/front', 'pim/router', 'danmyers-custom-entity/type-registry'], function (
  _,
  __,
  BaseController,
  router,
  TypeRegistry
) {
  return BaseController.extend({
    /**
     * {@inheritdoc}
     */
    renderForm: function () {
      return TypeRegistry.fetchAll().then(types => {
        const inMenu = _.values(types).filter(type => type.menu);
        const first = _.first(_.sortBy(_.sortBy(inMenu, type => __(type.label).toLowerCase()), 'menuPosition'));

        if (undefined === first) {
          router.redirectToRoute('pim_dashboard_index');

          return;
        }

        router.redirectToRoute('danmyers_custom_entity_index', {customEntityName: first.name});
      });
    },
  });
});
