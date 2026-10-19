'use strict';

/**
 * Code input of the creation modal. For custom entities generating their
 * code from their fields (CodeGeneratingInterface) there is no input; a code
 * error then means an entity with the same values already exists.
 */
define(['underscore', 'oro/translator', 'pim/form/common/creation/field', 'danmyers-custom-entity/type-registry'], function (
  _,
  __,
  CodeField,
  TypeRegistry
) {
  return CodeField.extend({
    /**
     * {@inheritdoc}
     */
    render: function () {
      if (!TypeRegistry.getCurrent().generatedCode) {
        return CodeField.prototype.render.apply(this, arguments);
      }

      const errors = (this.getRoot().validationErrors || []).filter(error => _.contains([error.path, error.attribute], 'code'));
      this.$el.html(
        0 === errors.length
          ? ''
          : `<div class="AknFieldContainer-validationErrors validation-errors">
              <span class="AknFieldContainer-validationError">
                <span class="error-message">${_.escape(__('danmyers_custom_entity.create.duplicate'))}</span>
              </span>
            </div>`
      );

      return this;
    },
  });
});
