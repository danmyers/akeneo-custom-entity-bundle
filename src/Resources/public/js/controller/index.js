'use strict';

/**
 * Index page of a custom entity type: the datagrid, its filters and the
 * creation button.
 */
define([
  'oro/translator',
  'pim/controller/front',
  'pim/form-builder',
  'pim/page-title',
  'danmyers-custom-entity/type-registry',
], function (__, BaseController, FormBuilder, PageTitle, TypeRegistry) {
  return BaseController.extend({
    /**
     * {@inheritdoc}
     */
    renderForm: function (route) {
      return TypeRegistry.fetch(route.params.customEntityName).then(type => {
        if (!this.active) {
          return;
        }

        TypeRegistry.setCurrent(type);
        PageTitle.set({'custom entity.label': __(type.label)});

        return FormBuilder.build(type.forms.index).then(form => {
          form.setElement(this.$el).render();

          return form;
        });
      });
    },
  });
});
