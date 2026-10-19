'use strict';

/**
 * "Custom entity" selector added to Akeneo's simple CSV / XLSX job profile
 * forms. It only renders for the custom entity import and export jobs, so
 * the core forms can be shared without copying them.
 */
define([
  'underscore',
  'oro/translator',
  'pim/job/common/edit/field/select',
  'danmyers-custom-entity/type-registry',
], function (_, __, SelectField, TypeRegistry) {
  const JOB_NAMES = [
    'danmyers_custom_entity_csv_import',
    'danmyers_custom_entity_csv_export',
    'danmyers_custom_entity_xlsx_import',
    'danmyers_custom_entity_xlsx_export',
  ];

  return SelectField.extend({
    /**
     * {@inheritdoc}
     */
    initialize: function (meta) {
      meta.config = Object.assign(
        {
          fieldCode: 'configuration.custom_entity',
          label: 'danmyers_custom_entity.job.custom_entity.label',
          tooltip: 'danmyers_custom_entity.job.custom_entity.help',
          options: {},
        },
        meta.config || {}
      );

      return SelectField.prototype.initialize.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    configure: function () {
      return TypeRegistry.fetchAll().then(types => {
        this.config.options = {'': ''};
        _.sortBy(_.values(types), type => __(type.label).toLowerCase()).forEach(type => {
          this.config.options[type.name] = type.label;
        });

        return SelectField.prototype.configure.apply(this, arguments);
      });
    },

    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!this.isCustomEntityJob()) {
        this.$el.empty();

        return this;
      }

      return SelectField.prototype.render.apply(this, arguments);
    },

    /**
     * {@inheritdoc}
     */
    getValue: function () {
      const value = SelectField.prototype.getValue.apply(this, arguments);

      return null === value || undefined === value ? '' : value;
    },

    /**
     * {@inheritdoc}
     */
    getFieldValue: function () {
      const value = SelectField.prototype.getFieldValue.apply(this, arguments);

      return '' === value ? null : value;
    },

    /**
     * @return {boolean}
     */
    isCustomEntityJob: function () {
      return _.contains(JOB_NAMES, this.getFormData().job_name);
    },
  });
});
