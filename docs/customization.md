# Customizing

Everything the bundle generates can be replaced, one piece at a time, without forking it.

- [Fields](#fields)
- [Edit form, creation modal, index page](#edit-form-creation-modal-index-page)
- [Datagrid](#datagrid)
- [Normalization (API, history, exports)](#normalization)
- [Business logic: custom managers](#custom-managers)
- [REST action events](#rest-action-events)
- [Repository and selects](#repository-and-selects)
- [Disabling actions](#disabling-actions)
- [Reference data declared elsewhere](#reference-data-declared-elsewhere)
- [Multi select picker](#multi-select-picker)

## Fields

The edit form, the creation modal, the grid columns and filters, the updater and the normalizers all
work from one field schema, inferred from the Doctrine mapping (see the table in the README). Adjust it
per property:

```yaml
danmyers_custom_entity:
    entities:
        brand:
            class: App\Entity\Brand
            fields:
                website: { grid: false }            # form only
                foundedOn: { label: Founded, filter: true }
                legacyId: { form: false, grid: false }
                slogan: { type: textarea }
                sortOrder: { position: 5 }
```

Properties that are not mapped Doctrine fields (for example a setter computing several columns) are
still accepted by the updater, the REST API and the imports: the value is passed to the setter as is.

## Edit form, creation modal, index page

The screens are Akeneo form extensions (the Backbone "form extension" system of the PIM). The generic
trees shipped by the bundle are shared by every custom entity:

| Screen | Root | File |
|---|---|---|
| Index (grid) | `danmyers-custom-entity-index` | [index.yml](../src/Resources/config/form_extensions/index.yml) |
| Creation modal | `danmyers-custom-entity-create-modal` | [create.yml](../src/Resources/config/form_extensions/create.yml) |
| Edit form | `danmyers-custom-entity-edit-form` | [edit.yml](../src/Resources/config/form_extensions/edit.yml) |

To change the screen of one entity, copy the tree into
`<YourBundle>/Resources/config/form_extensions/*.yml`, rename every code (e.g. replace
`danmyers-custom-entity-edit-form` by `app-brand-edit-form`), change what you need, then point the
entity at your root:

```yaml
danmyers_custom_entity:
    entities:
        brand:
            class: App\Entity\Brand
            edit_form: app-brand-edit-form
```

Rebuild the frontend (`yarn run update-extensions && yarn run webpack`) after editing form
extensions.

Inside your tree you can mix:

- Akeneo's field modules, bound to a property with `fieldName`:

    ```yaml
    app-brand-edit-form-slogan:
        module: pim/form/common/fields/textarea
        parent: app-brand-edit-form-properties-general-section
        targetZone: content
        position: 30
        config:
            fieldName: slogan
            label: app.brand.slogan
    ```

- the bundle modules, which work for any custom entity:

    | Module | Role |
    |---|---|
    | `danmyers-custom-entity/form/fields` | Renders the inferred fields; config `exclude: [field, ...]`, `onlyRequired: true` |
    | `danmyers-custom-entity/form/edit/save` | Save button |
    | `danmyers-custom-entity/form/edit/delete` | Delete action, with the "used by products" message |
    | `danmyers-custom-entity/form/edit/title` | Entity label as page title |
    | `danmyers-custom-entity/form/edit/translations` | Label translations (translatable entities) |
    | `danmyers-custom-entity/form/edit/history` | History tab |
    | `danmyers-custom-entity/common/breadcrumbs` | Breadcrumbs and menu highlighting |
    | `danmyers-custom-entity/form/creation/modal` | Creation modal posting to the REST API |

- your own RequireJS modules. `require('danmyers-custom-entity/type-registry').getCurrent()` returns
  the configuration of the custom entity being displayed (name, label, ACLs, actions, field schema).

## Datagrid

The grid `danmyers-custom-entity-<name>-grid` is generated at runtime. To write your own, declare it in
`<YourBundle>/Resources/config/datagrid.yml` and reference it:

```yaml
danmyers_custom_entity:
    entities:
        brand:
            class: App\Entity\Brand
            datagrid: app-brand-grid
```

Start from the generated configuration, which you can print with:

```php
$container->get(DatagridConfigurationProvider::class)->buildConfiguration($registry->get('brand'));
```

Keep `repository_method: createDatagridQueryBuilder` and `repository_parameters: {custom_entity: brand}`
in the source so that rows contain `customEntityName` (used by the edit and delete links). The mass
action handlers are `danmyers_custom_entity_mass_delete` and `danmyers_custom_entity_quick_export`; both
need a `custom_entity: <name>` option on the mass action.

## Normalization

Three generic normalizers, registered with priority 100, serve every custom entity:

| Serializer tag | Format | Used by |
|---|---|---|
| `pim_standard_format_serializer.normalizer` | `standard` | REST API, product values |
| `pim_internal_api_serializer.normalizer` | `internal_api` | Edit form (standard + `meta`) |
| `pim_versioning.serializer.normalizer` | `flat` | History snapshots, CSV/XLSX exports |

To change the output of one entity, register a normalizer for it with a priority above 100:

```yaml
services:
    App\Normalizer\BrandNormalizer:
        arguments: ['@DanMyers\CustomEntityBundle\Normalizer\Standard\CustomEntityNormalizer']
        tags:
            - { name: pim_standard_format_serializer.normalizer, priority: 110 }
```

```php
final class BrandNormalizer implements NormalizerInterface
{
    public function __construct(private NormalizerInterface $generic) {}

    public function normalize($brand, $format = null, array $context = []): array
    {
        return $this->generic->normalize($brand, $format, $context) + ['logoUrl' => $this->urlOf($brand)];
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $data instanceof Brand && 'standard' === $format;
    }
}
```

## Custom managers

All reads and writes of the screens, the REST API, the imports and the mass actions go through a
manager. To add business rules (side effects, extra checks, events), implement
`CustomEntityManagerInterface` (extending `CustomEntityManager` is easiest), tag it and select it:

```yaml
services:
    App\Manager\BrandManager:
        parent: DanMyers\CustomEntityBundle\Manager\CustomEntityManager
        tags:
            - { name: danmyers_custom_entity.manager, alias: brand }

danmyers_custom_entity:
    entities:
        brand:
            class: App\Entity\Brand
            manager: brand
```

Saves and removals use Akeneo's base saver and remover, so you can also listen to the
`StorageEvents::PRE_SAVE`, `POST_SAVE`, `PRE_REMOVE` and `POST_REMOVE` events.

## REST action events

Every REST action dispatches `CustomEntityEvents::PRE_ACTION` and `POST_ACTION` with a
`CustomEntityActionEvent` (action name, definition, request, entity, response):

```php
final class BrandActionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CustomEntityEvents::PRE_ACTION => 'freezeArchivedBrands'];
    }

    public function freezeArchivedBrands(CustomEntityActionEvent $event): void
    {
        $entity = $event->getEntity();
        if (CustomEntityEvents::ACTION_UPDATE === $event->getAction() && $entity instanceof Brand && $entity->isArchived()) {
            $event->setResponse(new JsonResponse([['path' => 'code', 'message' => 'Archived brands are read-only.']], 400));
        }
    }
}
```

Setting a response in `PRE_ACTION` skips the action; in `POST_ACTION` it replaces the action's response.

## Repository and selects

`CustomEntityRepository` (or `TranslatableCustomEntityRepository`) provides the select2 search used by
the product reference data selects, the product grid filters and the selects of the edit forms. The
label shown is the property returned by `getLabelProperty()` (the translation for translatable
entities), entities are sorted by `sortOrder` descending, then by `getSortOrderColumn()`, then by code.

Extend the repository to change the search, e.g. to hide archived entities:

```php
class BrandRepository extends CustomEntityRepository
{
    protected function createSearchQueryBuilder(?string $search, array $options): QueryBuilder
    {
        return parent::createSearchQueryBuilder($search, $options)->andWhere('rd.archived = false');
    }
}
```

## Disabling actions

```yaml
danmyers_custom_entity:
    entities:
        country:
            class: App\Entity\Country
            actions:
                create: false       # no creation button, POST returns 404
                delete: false       # no delete action nor mass delete
                quick_export: false
```

## Reference data declared elsewhere

If `akeneo_pim_structure.reference_data` already declares a reference data with the same name and
class, it is kept as is. With the same name but another class, the container refuses to build:
rename the custom entity or set `reference_data: false`.

## Multi select picker

The product field of reference data multi select attributes is
`danmyers-custom-entity/product/field/reference-multi-select-field` (mapped on the
`akeneo-multi-select-reference-data-field` field type). It extends Akeneo's field and only switches to
the picker when the attribute's reference data is a custom entity visible to the user.

The modal itself is `danmyers-custom-entity/product/field/reference-picker`, usable from your own
modules:

```js
const ReferencePicker = require('danmyers-custom-entity/product/field/reference-picker');

ReferencePicker.open({
  customEntity: 'color',          // custom entity name
  title: 'Colors',
  subtitle: 'Pick colors',
  locale: 'en_US',                // locale of the labels
  existingCodes: ['red'],         // shown as "already added"
}).then(selected => {
  // [{code: 'blue', label: 'Blue'}, ...] in the order they were ticked; rejected on cancel
});
```

Results come from the `danmyers_custom_entity_rest_search` endpoint (25 per page), so the repository
search customizations above apply to the picker too.
