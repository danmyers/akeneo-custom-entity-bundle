'use strict';

/**
 * Label translations section of the edit form, displayed only for
 * translatable custom entities.
 */
define(['pim/common/properties/translation', 'pim/security-context', 'danmyers-custom-entity/type-registry'], function (
  Translation,
  SecurityContext,
  TypeRegistry
) {
  return Translation.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      const type = TypeRegistry.getCurrent();
      meta.config = Object.assign({fieldBaseId: 'danmyers_custom_entity_label_'}, meta.config || {}, {
        readOnly: !(type.actions.edit && SecurityContext.isGranted(type.acl.edit)),
      });

      return Translation.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    isReadOnly: function () {
      return this.config.readOnly;
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!TypeRegistry.getCurrent().translatable) {
        this.$el.empty();

        return this;
      }

      return Translation.prototype.render.apply(this, arguments);
    },
  });
});
