'use strict';

/**
 * Pagination of the current custom entity type datagrid.
 */
define(['oro/datagrid/pagination-input', 'danmyers-custom-entity/type-registry'], function (Pagination, TypeRegistry) {
  return Pagination.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (options) {
      options.config = Object.assign({}, options.config, {gridName: TypeRegistry.getCurrent().datagrid});

      return Pagination.prototype.initialize.apply(this, arguments);
    },
  });
});
