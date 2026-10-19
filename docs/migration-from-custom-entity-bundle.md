# Migrating from FriendsOfAkeneo/CustomEntityBundle

[FriendsOfAkeneo/CustomEntityBundle](https://github.com/FriendsOfAkeneo/CustomEntityBundle)
(`akeneo-labs/custom-entity-bundle`) supports Akeneo up to 4.0. This bundle covers the same features
on Akeneo 7. It was written from scratch, so it is not a drop-in replacement, but the concepts map
one to one and your database tables can be kept.

## What you no longer need

The original bundle needed, per entity: a `custom_entities.yml` entry, a reference data declaration,
a datagrid YAML file, three form extension files, a menu entry, ACL definitions, a standard format
normalizer, an `entities.yml` parameter for the history tab, and translations.

Here a single configuration entry replaces all of them. Delete those files once migrated.

## Step by step

### 1. Entities

| Original | This bundle |
|---|---|
| `Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity` | `DanMyers\CustomEntityBundle\Entity\AbstractCustomEntity` |
| `AbstractTranslatableCustomEntity` | `AbstractTranslatableCustomEntity` (same name) |
| translation extending `AbstractTranslation` | `AbstractCustomEntityTranslation` (adds `label`) |
| `AbstractCustomOption` / `AbstractTranslatableCustomOption` (deprecated) | add a `label` column, or make it translatable |
| `Entity\Repository\CustomEntityRepository` | `DanMyers\CustomEntityBundle\Entity\Repository\CustomEntityRepository` |
| `TranslatableCustomEntityRepository` | same name, new namespace |
| `getCustomEntityName()` | not needed: the name comes from the configuration |

The base class maps `code` (100 chars, unique), `sort_order`, `created` and `updated`. If your table
names its columns differently, override the mapping with `#[ORM\AttributeOverrides]` or rename the
columns. The original mapping used `sortOrder` as column name. To keep it:

```php
#[ORM\AttributeOverrides([new ORM\AttributeOverride(name: 'sortOrder', column: new ORM\Column(name: 'sortOrder', type: 'integer'))])]
class Color extends AbstractCustomEntity
```

Then compare the schema: `bin/console danmyers:custom-entity:install` prints the SQL that would be
needed. Review it before running it with `--force`.

### 2. Configuration

```yaml
# before: Resources/config/custom_entities.yml
custom_entities:
    color:
        entity_class: Acme\Bundle\CustomBundle\Entity\Color
        options:
            edit_form_extension: pim-color-edit-form

# before: config/packages/akeneo_pim_structure.yml
akeneo_pim_structure:
    reference_data:
        color: { class: Acme\Bundle\CustomBundle\Entity\Color, type: simple }
```

```yaml
# after: config/packages/danmyers_custom_entity.yml
danmyers_custom_entity:
    entities:
        color:
            class: Acme\Bundle\CustomBundle\Entity\Color
            reference_data: simple
```

**Keep the same name** (`color`) so that existing product attributes keep pointing to it: their
`reference_data_name` is that name. Remove the old `akeneo_pim_structure.reference_data` entry, or
leave it: an entry with the same name and class is accepted.

### 3. Screens

Delete your `form_extensions/<name>/{index,create,edit}.yml`, the menu entries and the datagrid file,
then check the generated screens. Most custom edit forms of the original bundle only listed fields:
the generated form shows every mapped field, and per-field overrides cover labels, ordering and hiding.
For a really custom layout, see [customization](customization.md#edit-form-creation-modal-index-page).

### 4. Normalizers

The generic normalizers handle scalar fields, dates, links to other custom entities (as codes), images
and translations. Remove standard normalizers that only listed fields. Keep the ones adding computed
data, and give them a priority above 100.

### 5. Permissions

The original ACLs (`acme_custom_<name>_index`…) were defined by each project. Here they are generated
as `danmyers_custom_entity_<name>_*`. To keep yours, set `acl_prefix: acme_custom_<name>`; the
suffixes are `_index`, `_create`, `_edit`, `_delete`, `_history`.

### 6. Jobs

| Original job | This bundle |
|---|---|
| `csv_reference_data_import` | `danmyers_custom_entity_csv_import` (also XLSX) |
| `csv_reference_data_export` | `danmyers_custom_entity_csv_export` (also XLSX) |
| `csv_reference_data_quick_export` | `danmyers_custom_entity_csv_quick_export` / `..._xlsx_quick_export`, created automatically |
| job parameter `reference_data_name` | `custom_entity` |

Recreate your import/export profiles with the *Custom Entity Connector*. File columns are compatible:
`code`, `label-<locale>`, property names (camelCase or snake_case), codes for links, `sortOrder`.

### 7. Routes

| Original | This bundle |
|---|---|
| `/reference-data/{customEntityName}` | `/custom-entity/{customEntityName}` |
| `pim_customentity_rest_*` | `danmyers_custom_entity_rest_*` |
| `pim_customentity_quickexport` | `danmyers_custom_entity_quick_export` |

### 8. Events and actions

The original "action" system (`ActionFactory`, action services configured per entity) is replaced by:

- [REST action events](customization.md#rest-action-events) to add checks or change responses;
- [custom managers](customization.md#custom-managers) for business logic;
- the `actions` configuration to disable features.

## Behaviour changes

- **Mass delete** removes entities one by one through the manager, so history entries are written and
  entities used by products are protected. The original bulk delete skipped both.
- **Quick export** honours "select all" and the grid filters, and offers XLSX.
- **Removal errors** return `{"message": "..."}` with HTTP 412 and are shown to the user.
- **History** is always enabled (entities implement Akeneo's `VersionableInterface`); hide the tab with
  `actions.history: false`.
