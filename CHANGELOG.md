# Changelog

## 1.0.0 (unreleased)

First release, for Akeneo PIM Community Edition 7.0.

- Custom entities declared with a Doctrine entity and one configuration entry.
- Generated menu, datagrid, creation modal and edit form, driven by the Doctrine mapping.
- Translatable labels, links to other custom entities (single and multiple), images.
- Registration as Akeneo reference data for simple/multi select attributes, with label search in the
  catalog locale.
- Removal guard for entities used by products and product models.
- History of every change.
- Mass delete, CSV and XLSX quick exports.
- CSV and XLSX import and export jobs.
- Generated ACL resources per entity.
- Internal REST API with pre/post action events, pluggable managers and normalizers.
- Modal picker for reference data multi select attributes on products: search, tick several custom
  entities across searches and add them at once, with a "Select all" of the loaded results. Other
  reference data keeps Akeneo's field.
- `CodeGeneratingInterface`: codes derived from the entity fields at creation, duplicates rejected,
  code-less import rows matched on the generated code.
- Mapped properties without setter are treated as computed (read-only, ignored by imports).
- Searches match every word of the query, in any order.
