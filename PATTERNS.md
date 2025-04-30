# Design Patterns & Best Practices

This document outlines the key architectural patterns used throughout the application.

## Table of Contents

- [Architectural Patterns](#architectural-patterns)
- [Design Patterns](#design-patterns)
- [SOLID Principles](#solid-principles)
- [Laravel Best Practices](#laravel-best-practices)

## Architectural Patterns

### Repository Pattern

The repository pattern decouples the data access layer from business logic:

```php
// Interface
interface ProductRepositoryInterface
{
    public function getAll();
    public function findById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}

// Implementation
class ProductRepository implements ProductRepositoryInterface
{
    protected $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    public function getAll()
    {
        return $this->model->all();
    }

    // Other methods...
}
```

**Why use it?**

- Makes code more testable by allowing mock repositories in tests
- Centralizes data access logic
- Enables easy swapping of data sources without affecting business logic

### Service Layer Pattern

The service layer encapsulates business logic:

```php
// Interface
interface ProductServiceInterface
{
    public function getAllProducts();
    public function getProductById($id);
    public function createProduct(array $data);
    public function updateProduct($id, array $data);
    public function deleteProduct($id);
}

// Implementation
class ProductService implements ProductServiceInterface
{
    protected $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getAllProducts()
    {
        return $this->repository->getAll();
    }

    // Additional business logic...
}
```

**Why use it?**

- Separates business logic from controllers
- Promotes reusability across controllers (web, API)
- Makes business rules explicit and testable

### Livewire Component Pattern

Livewire components use a reactive approach for dynamic UI elements:

```php
namespace App\Modules\Products\Livewire;

use Livewire\Component;
use App\Modules\Products\Services\Interfaces\ProductServiceInterface;

class ProductForm extends Component
{
    public $name;
    public $description;
    public $price;
    public $product;

    protected $rules = [
        'name' => 'required|min:3',
        'description' => 'required',
        'price' => 'required|numeric|min:0',
    ];

    public function mount($productId = null, ProductServiceInterface $productService)
    {
        if ($productId) {
            $this->product = $productService->getProductById($productId);
            $this->name = $this->product->name;
            $this->description = $this->product->description;
            $this->price = $this->product->price;
        }
    }

    public function save(ProductServiceInterface $productService)
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
        ];

        if ($this->product) {
            $productService->updateProduct($this->product->id, $data);
            $this->dispatch('productUpdated');
        } else {
            $productService->createProduct($data);
            $this->dispatch('productCreated');
        }

        $this->reset(['name', 'description', 'price']);
    }

    public function render()
    {
        return view('products::livewire.product-form');
    }
}
```

**Why use it?**

- Provides reactive UI updates without full page reloads
- Combines frontend and backend logic in a single file
- Simplifies state management

## Design Patterns

### Observer Pattern

Used for event-driven architecture:

```php
// Model with observer support
class Product extends Model
{
    // ...

    protected $dispatchesEvents = [
        'created' => ProductCreated::class,
        'updated' => ProductUpdated::class,
        'deleted' => ProductDeleted::class,
    ];
}

// Event class
class ProductCreated
{
    public $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }
}

// Listener
class NotifyAdminAboutNewProduct implements ShouldQueue
{
    public function handle(ProductCreated $event)
    {
        // Send notification
    }
}
```

### Factory Pattern

Used for creating objects:

```php
// Abstract factory interface
interface ReportGeneratorFactory
{
    public function createPdfReport(): ReportInterface;
    public function createCsvReport(): ReportInterface;
}

// Concrete factory
class SalesReportFactory implements ReportGeneratorFactory
{
    public function createPdfReport(): ReportInterface
    {
        return new SalesPdfReport();
    }

    public function createCsvReport(): ReportInterface
    {
        return new SalesCsvReport();
    }
}
```

### Decorator Pattern

Used to add functionality to objects dynamically:

```php
// Interface
interface ReportInterface
{
    public function generate(): string;
}

// Base implementation
class BasicReport implements ReportInterface
{
    public function generate(): string
    {
        return "Basic report content";
    }
}

// Decorator
class HtmlReportDecorator implements ReportInterface
{
    protected $report;

    public function __construct(ReportInterface $report)
    {
        $this->report = $report;
    }

    public function generate(): string
    {
        return '<html><body>' . $this->report->generate() . '</body></html>';
    }
}
```

## SOLID Principles

### Single Responsibility Principle

Each class has only one reason to change:

- Controllers handle HTTP requests only
- Repositories handle data access only
- Services handle business logic only

### Open/Closed Principle

Classes are open for extension but closed for modification:

- Use interfaces to define contracts
- Extend functionality through new implementations rather than modifying existing code

### Liskov Substitution Principle

Subtypes must be substitutable for their base types:

- All repository implementations can be used interchangeably where the interface is expected

### Interface Segregation Principle

Clients should not be forced to depend on methods they do not use:

- Use focused interfaces rather than large, general-purpose ones

### Dependency Inversion Principle

High-level modules should not depend on low-level modules:

- Controllers depend on service interfaces, not implementations
- Services depend on repository interfaces, not implementations

## Laravel Best Practices

### Route Organization

- Group routes by module
- Use route names with module prefix
- Apply middleware at the group level

```php
Route::prefix('products')
    ->middleware(['auth', 'verified'])
    ->name('products.')
    ->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        // More routes...
    });
```

### Validation

- Use form request classes for complex validation
- Use `$rules` property in Livewire components for simple validation

```php
// Form request
class StoreProductRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|min:3|max:255',
            'description' => 'required',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
        ];
    }
}
```

### Authorization

- Use policies for authorization logic
- Register policies in service providers

```php
class ProductPolicy
{
    public function view(User $user, Product $product)
    {
        return $user->hasPermission('view-products') || $product->user_id === $user->id;
    }

    // Other permission methods...
}
```

### Testing

- Write unit tests for services and repositories
- Write feature tests for HTTP endpoints and Livewire components

```php
// Service unit test
public function test_get_all_products_returns_collection()
{
    // Arrange
    $mockRepository = $this->createMock(ProductRepositoryInterface::class);
    $mockRepository->expects($this->once())
        ->method('getAll')
        ->willReturn(collect([new Product(), new Product()]));

    $service = new ProductService($mockRepository);

    // Act
    $result = $service->getAllProducts();

    // Assert
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertCount(2, $result);
}

// Controller feature test
public function test_index_returns_products_view()
{
    // Arrange
    $this->actingAs(User::factory()->create());

    // Act
    $response = $this->get(route('products.index'));

    // Assert
    $response->assertStatus(200);
    $response->assertViewIs('products::index');
    $response->assertViewHas('products');
}
```
