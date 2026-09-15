<?php

declare(strict_types=1);

namespace NgarakDev\Modularization\Tests\Feature;

use NgarakDev\Modularization\Tests\TestCase;

class GeneratorTest extends TestCase
{
    public function test_it_generates_a_module_with_repository_and_service(): void
    {
        $this->artisan('make:module', ['name' => 'Products'])
            ->expectsOutput('Module [Products] created successfully.')
            ->assertSuccessful();

        $this->assertFileExists($this->modulePath('Products', 'Models/Products.php'));
        $this->assertFileExists($this->modulePath('Products', 'Repositories/ProductsRepository.php'));
        $this->assertFileExists($this->modulePath('Products', 'Services/ProductsService.php'));
        $this->assertFileExists($this->modulePath('Products', 'Http/Controllers/ProductsController.php'));

        $interface = $this->files->get($this->modulePath('Products', 'Repositories/Interfaces/ProductsRepositoryInterface.php'));
        $this->assertStringContainsString('function getAll(', $interface);
        $this->assertStringContainsString('function findById(', $interface);
    }

    public function test_it_generates_api_and_views(): void
    {
        $this->artisan('module:make', [
            'name' => 'Customers',
            '--api' => true,
            '--with-views' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->modulePath('Customers', 'Http/Controllers/API/CustomersController.php'));
        $this->assertFileExists($this->modulePath('Customers', 'Routes/api.php'));
        $this->assertFileExists($this->modulePath('Customers', 'Resources/views/customers/index.blade.php'));
        $this->assertStringContainsString('apiResource', $this->files->get($this->modulePath('Customers', 'Routes/api.php')));
    }

    public function test_it_generates_livewire_only_without_controllers(): void
    {
        $this->artisan('module:make', [
            'name' => 'Inventory',
            '--with-livewire-only' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->modulePath('Inventory', 'Livewire/InventoryTable.php'));
        $this->assertFileExists($this->modulePath('Inventory', 'Resources/views/livewire/inventory-table.blade.php'));
        $this->assertFileDoesNotExist($this->modulePath('Inventory', 'Http/Controllers/InventoryController.php'));
    }

    public function test_it_generates_nested_resources_in_the_module_namespace(): void
    {
        $this->artisan('module:make', [
            'name' => 'Catalog',
            '--resource' => 'Product',
        ])->assertSuccessful();

        $model = $this->files->get($this->modulePath('Catalog', 'Models/Product.php'));
        $this->assertStringContainsString('namespace Modules\\Catalog\\Models;', $model);
        $this->assertStringContainsString('class Product', $model);
        $this->assertFileExists($this->modulePath('Catalog', 'Http/Controllers/ProductController.php'));
    }

    public function test_it_can_skip_repository_scaffolding(): void
    {
        $this->artisan('module:make', [
            'name' => 'Notes',
            '--no-repository' => true,
            '--no-service' => true,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($this->modulePath('Notes', 'Repositories/NotesRepository.php'));
        $this->assertFileDoesNotExist($this->modulePath('Notes', 'Services/NotesService.php'));
        $this->assertFileExists($this->modulePath('Notes', 'Models/Notes.php'));
    }

    public function test_it_generates_classes_inside_a_module(): void
    {
        $this->artisan('module:make', ['name' => 'Billing'])->assertSuccessful();

        $this->artisan('module:make-model', ['module' => 'Billing', 'name' => 'Invoice'])->assertSuccessful();
        $this->artisan('module:make-controller', ['module' => 'Billing', 'name' => 'Invoice'])->assertSuccessful();
        $this->artisan('module:make-repository', ['module' => 'Billing', 'name' => 'Invoice'])->assertSuccessful();
        $this->artisan('module:make-service', ['module' => 'Billing', 'name' => 'Invoice'])->assertSuccessful();
        $this->artisan('module:make-request', ['module' => 'Billing', 'name' => 'StoreInvoice'])->assertSuccessful();
        $this->artisan('module:make-job', ['module' => 'Billing', 'name' => 'SendInvoice'])->assertSuccessful();

        $this->assertFileExists($this->modulePath('Billing', 'Models/Invoice.php'));
        $this->assertFileExists($this->modulePath('Billing', 'Http/Controllers/InvoiceController.php'));
        $this->assertFileExists($this->modulePath('Billing', 'Repositories/InvoiceRepository.php'));
        $this->assertFileExists($this->modulePath('Billing', 'Jobs/SendInvoiceJob.php'));

        $controller = $this->files->get($this->modulePath('Billing', 'Http/Controllers/InvoiceController.php'));
        $this->assertStringContainsString('namespace Modules\\Billing\\Http\\Controllers;', $controller);
        $this->assertStringContainsString('class InvoiceController', $controller);
    }

    public function test_it_generates_nested_class_names(): void
    {
        $this->artisan('module:make', ['name' => 'Catalog'])->assertSuccessful();
        $this->artisan('module:make-model', [
            'module' => 'Catalog',
            'name' => 'Admin/Widget',
        ])->assertSuccessful();

        $path = $this->modulePath('Catalog', 'Models/Admin/Widget.php');
        $this->assertFileExists($path);
        $this->assertStringContainsString('namespace Modules\\Catalog\\Models\\Admin;', $this->files->get($path));
    }

    public function test_force_is_required_to_overwrite(): void
    {
        $this->artisan('make:module', ['name' => 'Shop'])->assertSuccessful();
        $this->files->put($this->modulePath('Shop', 'custom.txt'), 'keep');

        $this->artisan('make:module', ['name' => 'Shop'])
            ->expectsQuestion('Module [Shop] already exists. Do you want to overwrite it?', false)
            ->expectsOutput('Module creation aborted!')
            ->assertExitCode(1);

        $this->assertFileExists($this->modulePath('Shop', 'custom.txt'));

        $this->artisan('make:module', ['name' => 'Shop', '--force' => true])->assertSuccessful();
        $this->assertFileDoesNotExist($this->modulePath('Shop', 'custom.txt'));
    }

    public function test_both_command_names_work(): void
    {
        $this->artisan('make:module', ['name' => 'Alpha'])->assertSuccessful();
        $this->files->deleteDirectory($this->modulePath('Alpha'));
        $this->artisan('module:make', ['name' => 'Alpha'])->assertSuccessful();

        $this->assertDirectoryExists($this->modulePath('Alpha'));
    }
}
