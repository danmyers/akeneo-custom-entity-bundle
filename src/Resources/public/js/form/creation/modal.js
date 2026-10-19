'use strict';

/**
 * Creation modal of the current custom entity type. Posts the code, the
 * label (translatable entities) and the required fields, then opens the edit
 * page of the new entity.
 */
define([
  'jquery',
  'underscore',
  'oro/translator',
  'routing',
  'pim/form/common/creation/modal',
  'oro/loading-mask',
  'pim/router',
  'danmyers-custom-entity/type-registry',
], function ($, _, __, Routing, BaseModal, LoadingMask, router, TypeRegistry) {
  return BaseModal.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      const type = TypeRegistry.getCurrent();
      meta.config = Object.assign(
        {
          labels: {title: 'pim_common.create', subTitle: type.label},
          picture: 'illustrations/Groups.svg',
          successMessage: 'danmyers_custom_entity.create.success',
        },
        meta.config || {}
      );
      this.type = type;

      return BaseModal.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    confirmModal: function (modal, deferred) {
      this.save().done(entity => {
        modal.close();
        modal.remove();
        deferred.resolve();
        this.postSuccess(entity);

        router.redirectToRoute('danmyers_custom_entity_edit', {
          customEntityName: this.type.name,
          id: entity.meta.id,
        });
      });
    },

    /**
     * {@inheritdoc}
     */
    save: function () {
      this.validationErrors = [];

      const loadingMask = new LoadingMask();
      this.$el.empty().append(loadingMask.render().$el.show());

      const data = $.extend({}, this.getFormData(), this.config.defaultValues || {});

      return $.ajax({
        url: Routing.generate('danmyers_custom_entity_rest_create', {customEntityName: this.type.name}),
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
      })
        .fail(response => {
          if (response.responseJSON) {
            this.getRoot().trigger('pim_enrich:form:entity:bad_request', {
              sentData: this.getFormData(),
              response: response.responseJSON.values || [],
            });
          }

          this.validationErrors = response.responseJSON
            ? this.normalize(response.responseJSON)
            : [{message: __('pim_enrich.entity.fallback.generic_error')}];
          this.render();
        })
        .always(() => loadingMask.remove());
    },
  });
});
