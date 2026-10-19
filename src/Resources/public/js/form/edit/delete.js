'use strict';

/**
 * Delete action of the custom entity edit form. Entities still used by
 * products cannot be deleted: the backend message is displayed.
 */
define([
  'jquery',
  'underscore',
  'oro/translator',
  'routing',
  'pim/form/common/delete',
  'pim/router',
  'oro/loading-mask',
  'oro/messenger',
  'pim/security-context',
  'danmyers-custom-entity/type-registry',
], function ($, _, __, Routing, BaseDelete, router, LoadingMask, messenger, SecurityContext, TypeRegistry) {
  return BaseDelete.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      meta.config = _.extend(
        {
          trans: {
            title: 'danmyers_custom_entity.delete.confirm',
            content: 'danmyers_custom_entity.delete.content',
            success: 'danmyers_custom_entity.delete.success',
            fail: 'danmyers_custom_entity.delete.fail',
            subTitle: TypeRegistry.getCurrent().label,
            buttonText: 'pim_common.delete',
          },
        },
        meta.config || {}
      );

      return BaseDelete.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      const type = TypeRegistry.getCurrent();
      if (!type.actions.delete || !SecurityContext.isGranted(type.acl.delete)) {
        this.$el.remove();

        return this;
      }

      return BaseDelete.prototype.render.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    doDelete: function () {
      const meta = this.getFormData().meta;
      const loadingMask = new LoadingMask();
      loadingMask.render().$el.appendTo(this.getRoot().$el).show();

      $.ajax({
        url: Routing.generate('danmyers_custom_entity_rest_delete', {customEntityName: meta.customEntityName, id: meta.id}),
        type: 'DELETE',
      })
        .done(() => {
          messenger.notify('success', __(this.config.trans.success));
          router.redirectToRoute('danmyers_custom_entity_index', {customEntityName: meta.customEntityName});
        })
        .fail(xhr => {
          const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : __(this.config.trans.fail);
          messenger.notify('error', message);
        })
        .always(() => loadingMask.hide().$el.remove());
    },
  });
});
