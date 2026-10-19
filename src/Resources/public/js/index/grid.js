'use strict';

/**
 * Datagrid of the current custom entity type.
 */
define(['pim/form/common/index/grid', 'danmyers-custom-entity/type-registry'], function (BaseGrid, TypeRegistry) {
  return BaseGrid.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      meta.config = Object.assign({}, meta.config, {alias: TypeRegistry.getCurrent().datagrid});

      return BaseGrid.prototype.initialize.apply(this, arguments);
    },
  });
});
