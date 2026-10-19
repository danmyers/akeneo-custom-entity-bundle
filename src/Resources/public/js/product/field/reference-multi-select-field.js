'use strict';

/**
 * Product edit field of "reference data multi select" attributes.
 *
 * When the reference data is a custom entity the user can see, values are
 * added through a modal (search, tick several results, add them at once);
 * the selected values stay displayed and removable as chips. Any other
 * reference data keeps Akeneo's original behaviour.
 */
define([
  'jquery',
  'underscore',
  'oro/translator',
  'pim/reference-multi-select-field',
  'danmyers-custom-entity/type-registry',
  'danmyers-custom-entity/product/field/reference-picker',
], function ($, _, __, BaseField, TypeRegistry, ReferencePicker) {
  return BaseField.extend({
    customEntityType: null,

    events: function () {
      return _.extend({}, _.result(BaseField.prototype, 'events'), {
        'click .danmyers-ReferencePicker-open': 'openPicker',
      });
    },

    /**
     * {@inheritdoc}
     *
     * Resolves whether the attribute is backed by a custom entity before
     * rendering.
     */
    getTemplateContext: function () {
      return $.when(BaseField.prototype.getTemplateContext.apply(this, arguments), this.resolveCustomEntityType()).then(
        templateContext => {
          templateContext.customEntityPicker = null !== this.customEntityType && this.isEditable();

          return templateContext;
        }
      );
    },

    /**
     * @return {Promise<Object|null>} the custom entity type, or null for other reference data
     */
    resolveCustomEntityType: function () {
      return TypeRegistry.fetchAll().then(
        types => {
          this.customEntityType = types[this.attribute.reference_data_name] || null;

          return this.customEntityType;
        },
        () => {
          this.customEntityType = null;

          return $.Deferred().resolve(null).promise();
        }
      );
    },

    /**
     * {@inheritdoc}
     */
    renderInput: function (context) {
      const input = BaseField.prototype.renderInput.apply(this, arguments);
      if (!context.customEntityPicker) {
        return input;
      }

      return `${input}
        <div class="AknFieldContainer-iconsContainer">
          <span
            class="AknIconButton AknIconButton--plus danmyers-ReferencePicker-open"
            data-toggle="tooltip"
            data-placement="top"
            title="${_.escape(__('danmyers_custom_entity.picker.open'))}"
          ></span>
        </div>`;
    },

    /**
     * {@inheritdoc}
     *
     * In picker mode, the select only displays (and removes) values: its own
     * search dropdown is disabled in favour of the modal.
     */
    postRender: function () {
      BaseField.prototype.postRender.apply(this, arguments);

      if (null === this.customEntityType || !this.isEditable()) {
        return;
      }

      const $select = this.$('.field-input:first input.select-field');
      $select.on('select2-opening', event => event.preventDefault());
      this.$('.field-input:first').addClass('danmyers-ReferencePicker-field');
    },

    /**
     * Opens the modal and appends the picked codes to the current value.
     */
    openPicker: function () {
      if (null === this.customEntityType || !this.isEditable()) {
        return;
      }

      const value = this.getCurrentValue();
      const currentCodes = value && Array.isArray(value.data) ? value.data : [];

      ReferencePicker.open({
        customEntity: this.customEntityType.name,
        title: this.getLabel(),
        subtitle: __(this.customEntityType.label),
        locale: this.context.locale,
        existingCodes: currentCodes,
      }).then(selected => {
        const added = _.pluck(selected, 'code').filter(code => !_.contains(currentCodes, code));
        if (0 === added.length) {
          return;
        }

        this.choicePromise = null;
        this.setCurrentValue(currentCodes.concat(added));
        this.render();
      });
    },
  });
});
