'use strict';

/**
 * Full page modal to pick several custom entities at once: search, tick the
 * results, and add every ticked entity in one go. Ticked entities are kept
 * across searches and listed on the right side.
 *
 *     ReferencePicker.open({customEntity, title, subtitle, locale, existingCodes})
 *       .then(selected => ...); // [{code, label}], in the order they were ticked
 *
 * The promise is rejected when the modal is cancelled.
 */
define([
  'jquery',
  'underscore',
  'backbone',
  'oro/translator',
  'routing',
  'oro/messenger',
  'danmyers-custom-entity/template/product/reference-picker',
  'bootstrap-modal',
], function ($, _, Backbone, __, Routing, messenger, template) {
  const PAGE_SIZE = 25;

  const modalTemplate = _.template(`
    <div class="AknFullPage AknFullPage--full AknFullPage--fixedWidth danmyers-ReferencePickerModal">
      <div class="AknFullPage-content">
        <div class="AknFullPage-titleContainer">
          <div class="AknFullPage-subTitle"><%- subtitle %></div>
          <div class="AknFullPage-title"><%- title %></div>
        </div>
        <div class="modal-body"></div>
        <div class="AknButtonList danmyers-ReferencePickerModal-buttons">
          <div class="AknButton AknButton--grey AknButtonList-item cancel"><%- cancelText %></div>
          <div class="AknButton AknButtonList-item AknButton--apply ok"><%- okText %></div>
        </div>
      </div>
    </div>
    <div class="AknFullPage-cancel cancel"></div>
  `);

  const Picker = Backbone.View.extend({
    template: _.template(template),

    events: {
      'input .danmyers-ReferencePicker-searchInput': 'onSearchInput',
      'keydown .danmyers-ReferencePicker-searchInput': 'onSearchKeydown',
      'change .danmyers-ReferencePicker-checkbox': 'onToggle',
      'click .danmyers-ReferencePicker-more': 'loadMore',
      'click .danmyers-ReferencePicker-unselect': 'onUnselect',
      'click .danmyers-ReferencePicker-clear': 'onClear',
      'change .danmyers-ReferencePicker-selectAll': 'onSelectAll',
    },

    /**
     * @param {Object}   options
     * @param {string}   options.customEntity  name of the custom entity type to search
     * @param {string}   options.locale        catalog locale of the labels
     * @param {string[]} options.existingCodes codes already in the product value
     */
    initialize: function (options) {
      this.customEntity = options.customEntity;
      this.locale = options.locale;
      this.existingCodes = options.existingCodes || [];
      this.term = '';
      this.page = 1;
      this.more = false;
      this.loading = false;
      this.results = [];
      this.selected = []; // [{code, label}] in tick order
      this.request = null;
      this.searchDebounced = _.debounce(this.search.bind(this), 250);
    },

    render: function () {
      const hadFocus = this.$('.danmyers-ReferencePicker-searchInput').is(':focus');
      const listScroll = this.$('.danmyers-ReferencePicker-results').scrollTop();
      const selectAllState = this.getSelectAllState();

      this.$el.html(
        this.template({
          __: __,
          _: _,
          term: this.term,
          results: this.results,
          more: this.more,
          loading: this.loading,
          existingCodes: this.existingCodes,
          selected: this.selected,
          selectedCodes: _.pluck(this.selected, 'code'),
          selectableCount: this.getSelectableResults().length,
          selectAllState: selectAllState,
        })
      );

      // the dash state of a checkbox can only be set from javascript
      this.$('.danmyers-ReferencePicker-selectAll').prop('indeterminate', 'some' === selectAllState);

      this.$('.danmyers-ReferencePicker-results').scrollTop(listScroll || 0);
      if (hadFocus) {
        const input = this.$('.danmyers-ReferencePicker-searchInput').get(0);
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
      }
      this.trigger('selection:change', this.selected.length);

      return this;
    },

    /**
     * Fetches the first page of results for the current search term.
     */
    search: function () {
      this.page = 1;
      this.results = [];
      this.fetch();
    },

    loadMore: function () {
      if (this.loading || !this.more) {
        return;
      }
      this.page += 1;
      this.fetch();
    },

    fetch: function () {
      if (null !== this.request) {
        this.request.abort();
      }

      this.loading = true;
      this.render();

      const page = this.page;
      this.request = $.getJSON(
        Routing.generate('danmyers_custom_entity_rest_search', {customEntityName: this.customEntity}),
        {search: this.term, locale: this.locale, options: {limit: PAGE_SIZE, page: page}}
      );

      this.request
        .done(response => {
          this.results = 1 === page ? response.results : this.results.concat(response.results);
          this.more = response.more;
        })
        .fail((xhr, status) => {
          if ('abort' !== status) {
            messenger.notify('error', __('danmyers_custom_entity.picker.search_failed'));
          }
        })
        .always((xhr, status) => {
          if ('abort' === status) {
            return;
          }
          this.request = null;
          this.loading = false;
          this.render();
        });
    },

    onSearchInput: function (event) {
      this.term = event.target.value;
      this.searchDebounced();
    },

    onSearchKeydown: function (event) {
      if ('Enter' === event.key) {
        event.preventDefault();
        this.term = event.target.value;
        this.searchDebounced.cancel();
        this.search();
      }
    },

    onToggle: function (event) {
      const code = String(event.target.dataset.code);
      if (event.target.checked) {
        if (!_.findWhere(this.selected, {code: code})) {
          this.selected.push({code: code, label: event.target.dataset.label});
        }
      } else {
        this.selected = _.reject(this.selected, item => item.code === code);
      }
      this.render();
    },

    /**
     * Loaded results that can be ticked: the ones not already on the product.
     *
     * @return {Array<{id: string, text: string}>}
     */
    getSelectableResults: function () {
      return this.results.filter(item => !_.contains(this.existingCodes, item.id));
    },

    /**
     * @return {string} "none", "some" or "all" of the selectable loaded results are ticked
     */
    getSelectAllState: function () {
      const selectable = this.getSelectableResults();
      const selectedCodes = _.pluck(this.selected, 'code');
      const ticked = selectable.filter(item => _.contains(selectedCodes, item.id)).length;

      if (0 === ticked) {
        return 'none';
      }

      return ticked === selectable.length ? 'all' : 'some';
    },

    /**
     * Ticks every selectable loaded result, or unticks them all when they
     * already are. Only acts on what is loaded: results loaded later arrive
     * unticked.
     */
    onSelectAll: function () {
      const selectable = this.getSelectableResults();

      if ('all' === this.getSelectAllState()) {
        const codes = _.pluck(selectable, 'id');
        this.selected = _.reject(this.selected, item => _.contains(codes, item.code));
      } else {
        const selectedCodes = _.pluck(this.selected, 'code');
        selectable
          .filter(item => !_.contains(selectedCodes, item.id))
          .forEach(item => this.selected.push({code: item.id, label: item.text}));
      }

      this.render();
    },

    onUnselect: function (event) {
      const code = String(event.currentTarget.dataset.code);
      this.selected = _.reject(this.selected, item => item.code === code);
      this.render();
    },

    onClear: function () {
      this.selected = [];
      this.render();
    },

    getSelected: function () {
      return this.selected.slice();
    },

    remove: function () {
      if (null !== this.request) {
        this.request.abort();
      }

      return Backbone.View.prototype.remove.apply(this, arguments);
    },
  });

  return {
    /**
     * @return {Promise<Array<{code: string, label: string}>>}
     */
    open: function (options) {
      const deferred = $.Deferred();
      const picker = new Picker(options);

      const modal = new Backbone.BootstrapModal({
        template: modalTemplate,
        title: options.title,
        subtitle: options.subtitle || '',
        okText: __('danmyers_custom_entity.picker.add', {count: 0}, 0),
        cancelText: __('pim_common.cancel'),
        okCloses: false,
        content: '',
      });
      modal.open();
      modal.$el.addClass('danmyers-ReferencePickerModal-container');

      const $ok = modal.$('.ok');
      const updateOk = count => {
        $ok.text(__('danmyers_custom_entity.picker.add', {count: count}, count));
        $ok.toggleClass('AknButton--disabled', 0 === count);
      };
      picker.on('selection:change', updateOk);

      picker.setElement(modal.$('.modal-body')).render();
      picker.search();
      modal.$('.danmyers-ReferencePicker-searchInput').trigger('focus');

      modal.on('ok', () => {
        const selected = picker.getSelected();
        if (0 === selected.length) {
          return;
        }
        picker.remove();
        modal.close();
        deferred.resolve(selected);
      });
      modal.on('cancel', () => {
        picker.remove();
        deferred.reject();
      });

      return deferred.promise();
    },
  };
});
