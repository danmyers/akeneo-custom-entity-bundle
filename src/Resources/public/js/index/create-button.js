'use strict';

/**
 * Creation button of the index page, opening the creation modal of the
 * current custom entity type. Hidden when creation is disabled or denied.
 */
define(['pim/form/common/index/create-button', 'pim/security-context', 'danmyers-custom-entity/type-registry'], function (
  CreateButton,
  SecurityContext,
  TypeRegistry
) {
  return CreateButton.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      const type = TypeRegistry.getCurrent();
      meta.config = Object.assign({iconName: 'plus', title: 'pim_common.create'}, meta.config, {
        modalForm: type.forms.create,
      });

      return CreateButton.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      const type = TypeRegistry.getCurrent();
      if (!type.actions.create || !SecurityContext.isGranted(type.acl.create)) {
        this.$el.empty();

        return this;
      }

      return CreateButton.prototype.render.apply(this, arguments);
    },
  });
});
