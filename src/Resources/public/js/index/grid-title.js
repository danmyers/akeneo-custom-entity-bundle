'use strict';

/**
 * Title of the index page: "<count> <type label>".
 */
define(['pim/common/grid-title', 'oro/translator', 'danmyers-custom-entity/type-registry'], function (
  GridTitle,
  __,
  TypeRegistry
) {
  return GridTitle.extend({
    /**
     * {@inheritdoc}
     */
    render: function () {
      const label = __(TypeRegistry.getCurrent().label);
      this.$el.text(null === this.count ? label : `${this.count} ${label}`);
    },
  });
});
