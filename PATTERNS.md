# Patterns

This package can generate repository and service classes. They are optional.

## When a repository helps

Use a repository when a module has query logic you want to reuse and test without the HTTP layer: filtering, persistence against more than one source, or a boundary you expect to mock.

Do not generate a repository that only forwards every Eloquent method. The bundled stubs expose a small set: `all` / `getAll`, `find` / `findById`, `paginate`, `create`, `update`, `delete`.

```php
public function findById(int|string $id): Product
{
    return $this->model->findOrFail($id);
}
```

Skip scaffolding with `--no-repository` or `scaffold.repositories = false`.

## When a service helps

Use a service when a use case has rules beyond “save this array”: pricing, status transitions, events, talking to another module.

```php
public function create(array $data): Product
{
    $product = $this->repository->create($data);
    event(new ProductCreated($product));

    return $product;
}
```

A controller that only calls `$product->save()` does not need a service. Use `--no-service` in that case.

## SOLID mapping

- Controllers handle HTTP.
- Services handle application/domain rules.
- Repositories handle persistence.
- Modules communicate through events and service interfaces, not by reaching into another module’s Eloquent models when a contract exists.

## Laravel conventions

Prefer Laravel’s own tools inside a module: form requests, policies, jobs, notifications, factories, seeders. The generators place those classes in the module namespace; they are ordinary Laravel classes.

Livewire components belong in `Livewire/` and are registered only when Livewire is installed.

## Testing

Bind a fake repository or service in a feature test:

```php
$this->app->instance(ProductRepositoryInterface::class, $fake);
```

Or skip the extra layers and test Eloquent models directly when the module has no repository.
