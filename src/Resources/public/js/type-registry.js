'use strict';

/**
 * Loads (once) and exposes the configured custom entity types visible to the
 * current user, as returned by the "danmyers_custom_entity_rest_types" route:
 * name, label, ACLs, enabled actions, forms, datagrid, field schema...
 *
 * It also holds the type of the page being displayed, so that the generic
 * index, creation and edit extensions know which custom entity they serve.
 */
define(['jquery', 'underscore', 'routing'], function ($, _, Routing) {
  let typesPromise = null;
  let current = null;

  return {
    /**
     * @return {Promise<Object>} types indexed by name
     */
    fetchAll: function () {
      if (null === typesPromise) {
        typesPromise = $.getJSON(Routing.generate('danmyers_custom_entity_rest_types'))
          .then(function (types) {
            return _.isArray(types) ? {} : types;
          })
          .promise();
      }

      return typesPromise;
    },

    /**
     * @param {string} name
     *
     * @return {Promise<Object>} rejected when the type does not exist or is not visible
     */
    fetch: function (name) {
      return this.fetchAll().then(function (types) {
        if (!_.has(types, name)) {
          return $.Deferred().reject().promise();
        }

        return types[name];
      });
    },

    /**
     * Sets the custom entity type of the page being rendered.
     *
     * @param {Object} type
     */
    setCurrent: function (type) {
      current = type;
    },

    /**
     * @return {Object|null}
     */
    getCurrent: function () {
      return current;
    },

    /**
     * Forgets the cached types (after a configuration change in tests).
     */
    clear: function () {
      typesPromise = null;
      current = null;
    },
  };
});
