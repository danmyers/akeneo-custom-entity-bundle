'use strict';

/**
 * Label input of the creation modal, for translatable custom entities. The
 * label is created in the current catalog locale.
 */
define(['underscore', 'oro/translator', 'pim/form', 'pim/user-context', 'pim/template/form/creation/field', 'danmyers-custom-entity/type-registry'], function (
  _,
  __,
  BaseForm,
  UserContext,
  template,
  TypeRegistry
) {
  return BaseForm.extend({
    template: _.template(template),
    events: {
      'keyup input': 'updateModel',
      'change input': 'updateModel',
    },

    updateModel: function (event) {
      const labels = Object.assign({}, this.getFormData().labels || {});
      labels[UserContext.get('catalogLocale')] = event.target.value || '';
      this.getFormModel().set('labels', labels);
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!TypeRegistry.getCurrent().translatable) {
        this.$el.empty();

        return this;
      }

      const locale = UserContext.get('catalogLocale');
      const errors = this.getRoot().validationErrors || [];

      this.$el.html(
        this.template({
          identifier: 'label',
          label: `${__('pim_common.label')} (${locale})`,
          requiredLabel: '',
          errors: errors.filter(error => _.contains(['labels', `labels[${locale}]`], error.path)),
          value: (this.getFormData().labels || {})[locale] || '',
        })
      );
      this.delegateEvents();

      return this;
    },
  });
});
