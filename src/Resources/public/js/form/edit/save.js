'use strict';

/**
 * Save button of the custom entity edit form.
 */
define([
  'jquery',
  'underscore',
  'oro/translator',
  'routing',
  'oro/messenger',
  'pim/form/common/save',
  'pim/field-manager',
  'pim/security-context',
  'danmyers-custom-entity/type-registry',
], function ($, _, __, Routing, messenger, BaseSave, FieldManager, SecurityContext, TypeRegistry) {
  return BaseSave.extend({
    updateSuccessMessage: __('danmyers_custom_entity.edit.update_success'),
    updateFailureMessage: __('danmyers_custom_entity.edit.update_fail'),

    /**
     * {@inheritdoc}
     */
    configure: function () {
      const type = TypeRegistry.getCurrent();
      if (!type.actions.edit || !SecurityContext.isGranted(type.acl.edit)) {
        return $.Deferred().resolve().promise();
      }

      return BaseSave.prototype.configure.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    save: function () {
      const notReadyFields = FieldManager.getNotReadyFields();
      if (0 < notReadyFields.length) {
        const names = notReadyFields.map(field => (field.attribute ? field.attribute.code : String(field)));
        messenger.notify('error', __('danmyers_custom_entity.edit.fields_not_ready', {fields: names.join(', ')}));

        return;
      }

      const data = this.getFormData();
      const entity = _.omit(data, _.union(this.config.excludedProperties || [], ['meta', 'code']));

      this.showLoadingMask();
      this.getRoot().trigger('pim_enrich:form:entity:pre_save');

      return $.ajax({
        url: Routing.generate('danmyers_custom_entity_rest_update', {
          customEntityName: data.meta.customEntityName,
          id: data.meta.id,
        }),
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(entity),
      })
        .then(saved => {
          this.postSave(saved);
          this.setData(saved);
          this.getRoot().trigger('pim_enrich:form:entity:post_fetch', saved);
        })
        .fail(this.fail.bind(this))
        .always(this.hideLoadingMask.bind(this));
    },
  });
});
