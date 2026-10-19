# Developing the bundle

The bundle is developed inside a real Akeneo PIM, so that the screens, jobs and API can be tried as
you change them.

## Local setup with the PIM docker stack

With this repository and a `pim-community-dev` (or `pim-community-standard`) checkout side by side:

```
Projects/
├── akeneo-custom-entity-bundle/    # this repository
└── pim-community-dev/
```

1. Mount this repository inside the PIM project, in `docker-compose.override.yml`:

    ```yaml
    services:
      php:
        volumes: ['../akeneo-custom-entity-bundle:/srv/pim/packages/akeneo-custom-entity-bundle']
      fpm:
        volumes: ['../akeneo-custom-entity-bundle:/srv/pim/packages/akeneo-custom-entity-bundle']
      node:
        volumes: ['../akeneo-custom-entity-bundle:/srv/pim/packages/akeneo-custom-entity-bundle']
      httpd:
        volumes: ['../akeneo-custom-entity-bundle:/srv/pim/packages/akeneo-custom-entity-bundle:ro']
    ```

    Mount it **inside** the project (not next to it): Akeneo's asset and require.js tooling expects
    every bundle path to be under the project directory. Add `/packages/` to `.git/info/exclude`.

2. Declare it as a Composer path repository, symlinked, in the PIM `composer.json`:

    ```json
    "repositories": [
        {"type": "path", "url": "packages/akeneo-custom-entity-bundle", "options": {"symlink": true}}
    ],
    "require": {
        "danmyers/akeneo-custom-entity-bundle": "@dev"
    }
    ```

    and, to load the demo bundle, in `autoload.psr-4`:

    ```json
    "Acme\\Bundle\\CustomEntityDemoBundle\\": "vendor/danmyers/akeneo-custom-entity-bundle/examples/AcmeCustomEntityDemoBundle/"
    ```

    Never use `"symlink": false` with a bind mount over the copy: Composer would try to delete the
    mounted directory, which is this repository.

3. Install it. `pim-community-dev` reports itself as `dev-main`, which does not match the bundle's `^7.0` constraint, so give Composer its real version:

    ```bash
    docker compose run --rm -e COMPOSER_ROOT_VERSION=7.0.85 php php -d memory_limit=4G /usr/local/bin/composer update danmyers/akeneo-custom-entity-bundle
    ```

4. Register both bundles in `config/bundles.php`, import the routes (see the README), then:

    ```bash
    docker compose run --rm php php bin/console danmyers:custom-entity:install --force
    make cache assets javascript-dev css
    ```

The demo entities are `demo_color`, `demo_fabric`, `demo_brand` and `demo_pictogram`.

### After a change

| Changed | Run |
|---|---|
| PHP, services, routing | `make cache` (routes also need `make assets` for the JS router) |
| Doctrine mapping | `bin/console danmyers:custom-entity:install` (prints the SQL) |
| JS modules, `requirejs.yml`, form extensions | `make javascript-dev` |
| `jsmessages.*.yml` | `make assets javascript-dev` |

Production mode (`--env=prod`, used by `make assets`) does not check configuration changes: clear
`var/cache/prod` after registering the bundle or changing its configuration.

## Tests and checks

Run them from the PIM's PHP container, with the PIM's tools and autoloader:

```bash
docker compose run --rm -w /srv/pim/packages/akeneo-custom-entity-bundle php sh -c '
  php /srv/pim/vendor/bin/phpunit &&
  php -d memory_limit=2G /srv/pim/vendor/bin/phpstan analyse -c phpstan.neon.dist &&
  php /srv/pim/vendor/bin/php-cs-fixer fix --dry-run --diff'
```

Outside of docker, point `PIM_AUTOLOAD_PATH` to a PIM's `vendor/autoload.php`.

## Architecture

```
src/
├── Entity/              base classes and repositories developers extend
├── Registry/            the configured entity types (CustomEntityDefinition)
├── Schema/              field schema inferred from Doctrine metadata
├── Updater/             standard format → entity (UI, API, imports)
├── Normalizer/          entity → standard / internal_api / flat
├── Manager/             persistence and lifecycle, per entity alias
├── Controller/          internal REST API, quick export endpoint, option search
├── Datagrid/            runtime datagrid configuration and mass action handlers
├── Connector/           CSV/XLSX import and export job components
├── Usage/               "is this entity used by products" check
├── EventSubscriber/     removal guard on Akeneo storage events
├── Acl/                 generated ACL resources
├── Installer/           quick export job instances
└── Resources/
    ├── config/          services, routes, jobs, generic form extensions, requirejs
    └── public/js/       generic screens (type registry, controllers, form modules)
```

The frontend is generic: one set of form extension trees serves every custom entity. The
`danmyers_custom_entity_rest_types` endpoint returns each type's configuration and field schema, and
the controllers store the current type in `danmyers-custom-entity/type-registry` before building the
forms.
