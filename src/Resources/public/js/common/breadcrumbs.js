'use strict';

/**
 * Breadcrumbs of the custom entity pages: "Custom entities > <Type>", and the
 * entity label on edit pages. Highlights the matching menu tab and item.
 */
define([
  'jquery',
  'oro/translator',
  'pim/form',
  'oro/mediator',
  'pim/form-registry',
  'pim/router',
  'react',
  'akeneo-design-system',
  'danmyers-custom-entity/type-registry',
], function ($, __, BaseForm, mediator, FormRegistry, router, React, {Breadcrumb}, TypeRegistry) {
  return BaseForm.extend({
    events: {
      'click .breadcrumb-tab': 'redirectTab',
      'click .breadcrumb-item': 'redirectItem',
    },

    /**
     * {@inheritdoc}
     *
     * @param {string}  config.tab        The menu tab to highlight
     * @param {boolean} config.withEntity Display the entity label as last step
     */
    initialize: function (config) {
      this.config = config.config || {};

      return BaseForm.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    configure: function () {
      const type = TypeRegistry.getCurrent();
      mediator.trigger('pim_menu:highlight:tab', {extension: this.config.tab});
      if (null !== type) {
        mediator.trigger('pim_menu:highlight:item', {extension: type.menuItem});
      }
      if (this.config.withEntity) {
        this.listenTo(this.getRoot(), 'pim_enrich:form:entity:post_fetch', this.render);
      }

      return BaseForm.prototype.configure.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      const type = TypeRegistry.getCurrent();

      return $.when(FormRegistry.getFormMeta(this.config.tab)).then(metaTab => {
        const steps = [
          React.createElement(
            Breadcrumb.Step,
            {key: 'tab', className: 'breadcrumb-tab'},
            __(metaTab ? metaTab.config.title : 'danmyers_custom_entity.menu.tab')
          ),
        ];

        if (null !== type) {
          steps.push(React.createElement(Breadcrumb.Step, {key: 'item', className: 'breadcrumb-item'}, __(type.label)));
        }

        const meta = this.getFormData().meta;
        if (this.config.withEntity && meta && meta.label) {
          steps.push(React.createElement(Breadcrumb.Step, {key: 'entity'}, meta.label));
        }

        this.renderReact(Breadcrumb, {children: steps}, this.el);
        this.delegateEvents();
      });
    },

    redirectTab: function () {
      mediator.trigger('pim_menu:redirect:tab', {extension: this.config.tab});
    },

    redirectItem: function () {
      const type = TypeRegistry.getCurrent();
      if (null !== type) {
        router.redirectToRoute('danmyers_custom_entity_index', {customEntityName: type.name});
      }
    },
  });
});
