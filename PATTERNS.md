# Design Patterns & Best Practices

This document outlines the architectural patterns used in this package and best practices for module development.

## Repository Pattern

The repository pattern provides an abstraction layer between your business logic and data access.

### Why Use It?

- **Testability**: Mock repositories in tests without touching the database
- **Flexibility**: Swap data sources without changing business logic
- **Single Responsibility**: Data access logic stays in one place
- **Reusability**: Share data access code across services

### Implementation

```php
// Contract (Interface)
interface ProductsRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Products;
    public function findOrFail(int $id): Products;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Products;
    public function update(Products $model, array $data): Products;
    public function delete(Products $model): bool;
}

// Implementation
class ProductsRepository implements ProductsRepositoryInterface
{
    public function __construct(
        protected Products $model,
    ) {}

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(int $id): ?Products
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Products
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $data): Products
    {
        return $this->model->create($data);
    }

    public function update(Products $model, array $data): Products
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(Products $model): bool
    {
        return $model->delete();
    }
}
```

### When NOT to Use

For simple CRUD with no business logic, you can skip repositories and use Eloquent directly in your controllers. Repositories add value when:

- You need complex queries
- You want to test without database
- You might change data sources
- Multiple services need the same data access logic

## Service Layer Pattern

The service layer contains business logic that shouldn't live in controllers or models.

### Why Use It?

- **Reusability**: Same logic for web, API, CLI, and jobs
- **Testability**: Test business logic without HTTP layer
- **Separation**: Controllers stay thin
- **Organization**: Business rules are explicit

### Implementation

```php
// Contract
interface ProductsServiceInterface
{
    public function getAll(): Collection;
    public function findById(int $id): ?Products;
    public function create(array $data): Products;
    public function update(int $id, array $data): Products;
    public function delete(int $id): bool;
}

// Implementation
class ProductsService implements ProductsServiceInterface
{
    public function __construct(
        protected ProductsRepositoryInterface $repository,
    ) {}

    public function getAll(): Collection
    {
        return $this->repository->all();
    }

    public function create(array $data): Products
    {
        // Business logic before creation
        $data['slug'] = Str::slug($data['name']);
        
        // Create the product
        $product = $this->repository->create($data);
        
        // Business logic after creation
        event(new ProductCreated($product));
        
        return $product;
    }

    public function update(int $id, array $data): Products
    {
        $product = $this->repository->findOrFail($id);
        
        // Business rules
        if ($product->is_locked) {
            throw new ProductLockedException($product);
        }
        
        return $this->repository->update($product, $data);
    }
}
```

### Service Layer Guidelines

1. **One service per domain concept** - ProductsService, not ProductsAndCategoriesService
2. **Inject dependencies** - Don't use facades inside services
3. **Keep methods focused** - Each method does one thing
4. **Throw domain exceptions** - Let the controller handle HTTP responses

## Controller Patterns

### Thin Controllers

Controllers should only:

1. Validate input (via Form Requests)
2. Call services
3. Return responses

```php
class ProductsController extends Controller
{
    public function __construct(
        protected ProductsServiceInterface $service,
    ) {}

    public function index(): View
    {
        $products = $this->service->getAll();
        return view('products::index', compact('products'));
    }

    public function store(ProductsRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());
        return redirect()->route('products.index')
            ->with('success', 'Product created.');
    }
}
```

### API Controllers

Return consistent JSON responses:

```php
class ProductsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getAll(),
        ]);
    }

    public function store(ProductsRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated());
        
        return response()->json([
            'data' => $product,
            'message' => 'Product created successfully.',
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);
        
        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
```

## Dependency Injection

### Service Provider Bindings

```php
class ProductsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface to implementation
        $this->app->bind(
            ProductsRepositoryInterface::class,
            ProductsRepository::class
        );

        $this->app->bind(
            ProductsServiceInterface::class,
            ProductsService::class
        );
    }
}
```

### Constructor Injection

```php
class ProductsService implements ProductsServiceInterface
{
    public function __construct(
        protected ProductsRepositoryInterface $repository,
        protected EventDispatcher $events,
        protected CacheManager $cache,
    ) {}
}
```

## Form Requests

Use form requests for validation:

```php
class ProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or check permissions
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
        ];
    }
}
```

## Event-Driven Architecture

Use events for cross-module communication:

### Define Events

```php
// In Products module
class ProductCreated
{
    public function __construct(
        public readonly Products $product,
    ) {}
}

class ProductDeleted
{
    public function __construct(
        public readonly int $productId,
    ) {}
}
```

### Dispatch Events

```php
class ProductsService
{
    public function create(array $data): Products
    {
        $product = $this->repository->create($data);
        event(new ProductCreated($product));
        return $product;
    }
}
```

### Listen in Other Modules

```php
// In Orders module
class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            ProductDeleted::class,
            function (ProductDeleted $event) {
                // Remove product from pending orders
                $this->orderService->removeProduct($event->productId);
            }
        );
    }
}
```

## Testing Patterns

### Unit Testing Services

```php
class ProductsServiceTest extends TestCase
{
    private ProductsService $service;
    private ProductsRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->createMock(ProductsRepositoryInterface::class);
        $this->service = new ProductsService($this->repository);
    }

    public function test_create_adds_slug(): void
    {
        $data = ['name' => 'Test Product'];
        
        $this->repository
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function ($arg) {
                return $arg['slug'] === 'test-product';
            }));
        
        $this->service->create($data);
    }
}
```

### Feature Testing Controllers

```php
class ProductsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_products(): void
    {
        $products = Products::factory()->count(3)->create();
        
        $response = $this->get(route('products.index'));
        
        $response->assertStatus(200);
        $response->assertViewHas('products');
    }

    public function test_store_creates_product(): void
    {
        $data = [
            'name' => 'New Product',
            'price' => 99.99,
        ];
        
        $response = $this->post(route('products.store'), $data);
        
        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', $data);
    }
}
```

## SOLID Principles

### Single Responsibility

Each class has one reason to change:

- Controllers: Handle HTTP
- Services: Business logic
- Repositories: Data access
- Requests: Validation

### Open/Closed

Extend through interfaces, not modification:

```php
// Add a new implementation
class CachedProductsRepository implements ProductsRepositoryInterface
{
    public function __construct(
        private ProductsRepository $repository,
        private CacheManager $cache,
    ) {}

    public function all(): Collection
    {
        return $this->cache->remember('products', 3600, fn () => 
            $this->repository->all()
        );
    }
}
```

### Liskov Substitution

All implementations are interchangeable:

```php
// Both work wherever ProductsRepositoryInterface is expected
$repository = new ProductsRepository($model);
$repository = new CachedProductsRepository($repository, $cache);
```

### Interface Segregation

Keep interfaces focused:

```php
// Good: Focused interfaces
interface Searchable
{
    public function search(string $query): Collection;
}

interface Sortable
{
    public function sortBy(string $field, string $direction): Collection;
}

// Implement only what you need
class ProductsRepository implements 
    ProductsRepositoryInterface, 
    Searchable
{
    // ...
}
```

### Dependency Inversion

Depend on abstractions:

```php
// Good: Depends on interface
class ProductsService
{
    public function __construct(
        protected ProductsRepositoryInterface $repository,
    ) {}
}

// Bad: Depends on concrete class
class ProductsService
{
    public function __construct(
        protected ProductsRepository $repository,
    ) {}
}
```
