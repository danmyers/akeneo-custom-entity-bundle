'use strict';

/**
 * Edit page of a custom entity. The form rendered is the one named in the
 * entity "meta.form", so that a custom entity can declare its own edit form.
 */
define([
  'jquery',
  'oro/translator',
  'routing',
  'pim/controller/front',
  'pim/form-builder',
  'pim/page-title',
  'danmyers-custom-entity/type-registry',
], function ($, __, Routing, BaseController, FormBuilder, PageTitle, TypeRegistry) {
  return BaseController.extend({
    /**
     * {@inheritdoc}
     */
    renderForm: function (route) {
      const name = route.params.customEntityName;

      return $.when(
        TypeRegistry.fetch(name),
        $.getJSON(Routing.generate('danmyers_custom_entity_rest_get', {customEntityName: name, id: route.params.id}))
      ).then((type, response) => {
        if (!this.active) {
          return;
        }

        const entity = Array.isArray(response) ? response[0] : response;
        TypeRegistry.setCurrent(type);
        PageTitle.set({'custom entity.label': entity.meta.label, 'custom entity.type': __(type.label)});

        return FormBuilder.build(entity.meta.form || type.forms.edit).then(form => {
          this.on('pim:controller:can-leave', event => {
            form.trigger('pim_enrich:form:can-leave', event);
          });
          form.setData(entity);
          form.trigger('pim_enrich:form:entity:post_fetch', entity);
          form.setElement(this.$el).render();

          return form;
        });
      });
    },
  });
});
