'use strict';

/**
 * "Label translations" section of the edit form, rendered only for
 * translatable custom entities.
 */
define(['pim/common/simple-view', 'danmyers-custom-entity/type-registry'], function (SimpleView, TypeRegistry) {
  return SimpleView.extend({
    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!TypeRegistry.getCurrent().translatable) {
        this.$el.empty();

        return this;
      }

      return SimpleView.prototype.render.apply(this, arguments);
    },
  });
});
