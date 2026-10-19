'use strict';

/**
 * History tab of the edit form, listing the versions of the entity. Hidden
 * when history is disabled or denied for the custom entity type.
 */
define(['jquery', 'pim/common/tab/history', 'pim/security-context', 'danmyers-custom-entity/type-registry'], function (
  $,
  History,
  SecurityContext,
  TypeRegistry
) {
  return History.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      meta.config = Object.assign({title: 'pim_common.history'}, meta.config || {}, {
        class: TypeRegistry.getCurrent().name,
      });

      return History.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    configure: function () {
      const type = TypeRegistry.getCurrent();
      if (!type.actions.history || !SecurityContext.isGranted(type.acl.history)) {
        return $.Deferred().resolve().promise();
      }

      return History.prototype.configure.apply(this, arguments);
    },
  });
});
