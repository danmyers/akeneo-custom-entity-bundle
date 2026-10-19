'use strict';

/**
 * Title of the edit form: the entity label in the catalog locale, or its code.
 */
define(['pim/form/common/label', 'pim/user-context', 'pim/i18n'], function (BaseLabel, UserContext, i18n) {
  return BaseLabel.extend({
    /**
     * {@inheritdoc}
     */
    getLabel: function () {
      const data = this.getFormData();
      if (data.labels && !Array.isArray(data.labels)) {
        return i18n.getLabel(data.labels, UserContext.get('catalogLocale'), data.code);
      }

      return data.meta && data.meta.label ? data.meta.label : data.code;
    },
  });
});
