'use strict';

/**
 * Renders the fields of the current custom entity type from its schema
 * (inferred from Doctrine metadata), using Akeneo's own field components:
 *
 * - text, textarea, number, boolean, date: the matching core field
 * - datetime: a text field (ISO 8601)
 * - select / multiselect: async selects searching the target custom entity
 * - image: the core media field
 *
 * Config:
 * - onlyRequired {boolean} render only the required fields (creation modal)
 * - exclude      {string[]} field names not to render
 */
define([
  'underscore',
  'routing',
  'pim/form',
  'pim/security-context',
  'pim/form/common/fields/text',
  'pim/form/common/fields/textarea',
  'pim/form/common/fields/number',
  'pim/form/common/fields/boolean',
  'pim/form/common/fields/date',
  'pim/form/common/fields/simple-select-async',
  'pim/form/common/fields/multi-select-async',
  'pim/form/common/fields/media',
  'danmyers-custom-entity/type-registry',
], function (
  _,
  Routing,
  BaseForm,
  SecurityContext,
  TextField,
  TextareaField,
  NumberField,
  BooleanField,
  DateField,
  SimpleSelectAsync,
  MultiSelectAsync,
  MediaField,
  TypeRegistry
) {
  const FIELD_CLASSES = {
    text: TextField,
    datetime: TextField,
    textarea: TextareaField,
    number: NumberField,
    boolean: BooleanField,
    date: DateField,
    select: SimpleSelectAsync,
    multiselect: MultiSelectAsync,
    image: MediaField,
  };

  return BaseForm.extend({
    className: 'AknFormContainer AknFormContainer--withPadding danmyers-custom-entity-fields',

    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      this.config = Object.assign({onlyRequired: false, exclude: []}, meta.config || {});

      BaseForm.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    configure: function () {
      const type = TypeRegistry.getCurrent();
      const readOnly = !this.config.onlyRequired && !(type.actions.edit && SecurityContext.isGranted(type.acl.edit));

      _.sortBy(type.fields, 'position')
        .filter(field => field.form)
        .filter(field => !this.config.onlyRequired || field.required)
        .filter(field => !_.contains(this.config.exclude, field.name))
        // associations can only be edited when they target another custom entity
        .filter(field => !_.contains(['select', 'multiselect'], field.type) || null !== field.customEntity)
        .forEach((field, index) => {
          const FieldClass = FIELD_CLASSES[field.type];
          if (undefined === FieldClass) {
            return;
          }

          const extension = new FieldClass({
            config: {
              fieldName: field.name,
              label: field.label,
              required: field.required,
              readOnly: readOnly,
              defaultValue: 'boolean' === field.type ? false : undefined,
            },
          });

          if (null !== field.customEntity && _.isFunction(extension.setChoiceUrl)) {
            extension.setChoiceUrl(
              Routing.generate('danmyers_custom_entity_rest_search', {customEntityName: field.customEntity})
            );
            extension.allowClear = !field.required;
          }

          this.addExtension(`${this.code}-${field.name}`, extension, 'self', (index + 1) * 10);
        });

      return BaseForm.prototype.configure.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!this.configured) {
        return this;
      }

      this.$el.empty();

      return this.renderExtensions();
    },
  });
});
