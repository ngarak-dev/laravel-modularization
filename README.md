# Laravel Modularization

A Laravel package for organizing applications by **business domain**. Each module owns its routes, views, migrations, and (optionally) repositories and services.

This package is not a clone of `nwidart/laravel-modules`. It stays small, uses Laravel’s own service providers and generators, and treats the repository/service layers as optional scaffolding rather than a required architecture.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Livewire 3 is optional and only needed if you generate or auto-register Livewire components

## Installation

```bash
composer require ngarak-dev/laravel-modularization
php artisan vendor:publish --tag=modularization-config
```

Add the modules namespace to `composer.json` if the first `module:make` command does not do it for you:

```json
"autoload": {
    "psr-4": {
        "Modules\\": "modules/"
    }
}
```

Then run `composer dump-autoload`.

## Why modules?

Group code by domain (`Billing`, `Catalog`, `Users`) instead of by technical type. A team can work inside `modules/Billing` without hunting through `app/Http/Controllers`. Modules can be enabled, disabled, cached, and exported without rewriting the rest of the application.

Repositories and services are useful when a domain has real query or business rules. They are unnecessary for trivial CRUD — use Eloquent directly in those cases.

## Create a module

```bash
php artisan module:make Billing
php artisan module:list
```

`make:module` is an alias of `module:make`.

Useful flags:

```bash
php artisan module:make Catalog --api --with-views --resource=Product
php artisan module:make Inventory --with-livewire
php artisan module:make Invoices --requires=Users,Billing
php artisan module:make Notes --no-repository --no-service
```

`--force` overwrites an existing module. Without it, the command asks before deleting files and does nothing destructive in non-interactive CI unless `--force` is passed.

## Module structure

```
modules/Billing/
├── module.json                 # machine-readable contract
├── Config/config.php           # optional extra config
├── Database/Migrations/
├── Http/Controllers/
├── Models/
├── Providers/BillingServiceProvider.php
├── Repositories/
├── Routes/web.php
├── Resources/views/
└── Services/
```

The canonical directories are PascalCase (`Http`, `Routes`, `Database`, `Resources`). Discovery also accepts Laravel-style lowercase paths (`routes`, `database/migrations`) for older modules.

## Module contract (`module.json`)

```json
{
    "name": "Orders",
    "namespace": "Modules\\Orders",
    "provider": "Modules\\Orders\\Providers\\OrdersServiceProvider",
    "version": "1.0.0",
    "description": "Order management",
    "enabled": true,
    "requires": ["Users"]
}
```

Older modules that only have `Config/config.php` still work. Status is also controlled by a `.disabled` file so enable/disable survives cache rebuilds.

## Generating classes

```bash
php artisan module:make-model Catalog Product
php artisan module:make-controller Catalog Product
php artisan module:make-repository Catalog Product
php artisan module:make-service Catalog Product
php artisan module:make-request Catalog StoreProduct
php artisan module:make-resource Catalog Product
php artisan module:make-migration create_products_table Catalog --create=products
php artisan module:make-seeder Catalog Product
php artisan module:make-factory Catalog Product
php artisan module:make-policy Catalog Product
php artisan module:make-event Catalog ProductCreated
php artisan module:make-listener Catalog SendProductCreated
php artisan module:make-job Catalog SyncProduct
php artisan module:make-notification Catalog ProductPublished
php artisan module:make-command Catalog ImportProducts
php artisan module:make-test Catalog ProductTest
```

Nested names work: `php artisan module:make-model Catalog Admin/Widget`.

## Routes, views, translations, assets

Enabled modules automatically register:

- `Routes/web.php` with the `web` middleware
- `Routes/api.php` under the `api` prefix
- `Routes/livewire.php` when present
- Views as `billing::...` (the lowercased module name)
- Translations, migrations, and config files
- Livewire components when Livewire is installed

Route files are loaded in module-name order after dependency sorting, so load order is deterministic. Avoid colliding route names across modules (`billing.orders.index` vs `orders.index`).

Place Vite-friendly assets in `Resources/assets` and publish them with:

```bash
php artisan vendor:publish --tag=billing-assets
```

Or import them from your application’s Vite config. The package does not invent a frontend build.

Set `auto_register_controllers` to `false` if a module service provider already loads its own routes.

## Controllers, models, services, repositories

Generated controllers depend on a service interface when scaffolding is enabled. A typical flow:

```php
public function store(StoreProductRequest $request): RedirectResponse
{
    $this->service->create($request->validated());

    return redirect()->route('product.index');
}
```

The generated repository is a small Eloquent wrapper (`all`, `findById`, `create`, `update`, `delete`, `paginate`). It is not a proxy for every Eloquent method. Skip it with `--no-repository` when a model is enough.

The generated service is the place for domain rules. Skip it with `--no-service` when the controller would only forward to Eloquent.

## API and Livewire

`--api` adds an API controller and `Routes/api.php`.

`--with-livewire` adds table/form components. Livewire is a suggested dependency, not a hard requirement.

```bash
php artisan module:make-livewire Catalog ProductCard
```

Components are registered as `catalog.product-card`.

## Events, translations, auth, manager

```bash
php artisan module:make-event Catalog ProductCreated --listener=NotifyTeam
php artisan module:make-translation Catalog --languages=en,fr
php artisan module:make-auth Auth
php artisan module:make-manager
```

`module:make-auth` still scaffolds a Blade authentication module. `module:make-manager` still scaffolds a dashboard that lists modules.

## Dependencies

`requires` in `module.json` is a list of module names. The package:

1. Detects the dependency
2. Checks that it exists
3. Detects circular graphs
4. Boots modules in dependency order
5. Shows missing dependencies in `module:list`

Version constraints are not evaluated. Keep the list as names only.

`modularization.dependencies.fail_on_missing` defaults to `false` so a missing dependency skips that module instead of taking down the application. Set it to `true` if you want a boot-time exception.

## Enable, disable, list, cache

```bash
php artisan module:list
php artisan module:toggle Catalog --disable
php artisan module:toggle Catalog --enable
php artisan module:discover
php artisan module:cache
php artisan module:clear
```

`module:list` shows enabled, disabled, invalid, missing-dependency, and cached state.

In production, run `module:cache` during deploy (it is hooked into `php artisan optimize` on Laravel 11+). Changing enable/disable clears the cache so stale metadata cannot hide a disabled module. If a cache file exists but the configured `modules_path` changed, the cache is ignored.

## Testing modules

```bash
php artisan module:make-test Catalog ProductIndexTest
php artisan module:migrate Catalog
php artisan module:migrate-all --only-enabled
```

Feature tests for the package itself live in `tests/`. Application tests should treat a module like any other Laravel code: hit routes, assert views, bind fake repositories.

## Exporting a module as a package

```bash
php artisan module:export Catalog --vendor=Acme --force
```

This copies the module into `build/catalog` with a `composer.json` and a package service provider.

## Configuration

Publish `config/modularization.php` to change:

| Key | Purpose |
| --- | --- |
| `modules_path` | Directory relative to the app base path |
| `namespace` | Root PHP namespace (`Modules`) |
| `directories` | Folders created by `module:make` |
| `auto_register_controllers` | Load module route files |
| `auto_register_livewire` | Register Livewire components |
| `scaffold.repositories` / `scaffold.services` | Default scaffolding |
| `cache.path` / `cache.enabled` | Compiled module metadata |
| `dependencies.fail_on_missing` | Throw on missing `requires` |
| `update_composer` | Add the namespace to `composer.json` |

Invalid `modules_path` or `namespace` values fail with a clear error. Unused keys from earlier versions (`enforce_repository_pattern`) still exist so existing config files keep working.

## Helpers and facade

```php
module_path('Catalog');
module_path('Catalog', 'Http/Controllers');
modules_path();

use NgarakDev\Modularization\Facades\Modularization;

Modularization::hasModule('Catalog');
Modularization::isEnabled('Catalog');
Modularization::enable('Catalog');
Modularization::getModules();
```

## Upgrade notes (1.0 → 1.1)

- PHP 8.1+ and Laravel 10–12 remain the supported matrix. Livewire is no longer a hard Composer requirement.
- New modules get a `module.json`. Existing `Config/config.php` modules continue to load.
- `Modularization::enable()` / `disable()` now persist via `.disabled` (they previously only changed in-memory state).
- `module:make` no longer writes an unused `config/modules.php`.
- `directories` in config is now actually used when generating modules.
- Production apps should run `php artisan module:cache` after deploy.

No change is required to existing module class names, view namespaces, or `module:toggle` usage.

## License

MIT. See [LICENSE.md](LICENSE.md).
