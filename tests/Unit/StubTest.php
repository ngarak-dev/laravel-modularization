<?php

namespace NgarakDev\Modularization\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;
use NgarakDev\Modularization\Providers\ModularizationServiceProvider;

class StubTest extends TestCase
{
    protected $files;
    protected $stubsDirectory;

    protected function getPackageProviders($app)
    {
        return [
            ModularizationServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->stubsDirectory = dirname(__DIR__, 2) . '/stubs';
    }

    /** @test */
    public function it_has_required_stub_files()
    {
        // Check if stubs directory exists
        $this->assertTrue($this->files->isDirectory($this->stubsDirectory), 'Stubs directory does not exist');

        // Define the required stubs
        $requiredStubs = [
            'repository.stub',
            'repository-interface.stub',
            'service.stub',
            'service-interface.stub',
            'web-controller.stub',
            'api-controller.stub',
            'web-routes.stub',
            'api-routes.stub',
            'view-index.stub',
            'view-show.stub',
            'view-create.stub',
            'view-edit.stub',
            'livewire-table.stub',
            'livewire-form.stub',
            'module-layout.stub'
        ];

        // Check that each required stub exists
        foreach ($requiredStubs as $stub) {
            $this->assertTrue(
                $this->files->exists($this->stubsDirectory . '/' . $stub),
                "Required stub file '{$stub}' does not exist"
            );
        }
    }

    /** @test */
    public function repository_stubs_follow_pattern_correctly()
    {
        // Check repository interface stub
        $repoInterfaceContent = $this->files->get($this->stubsDirectory . '/repository-interface.stub');

        $this->assertStringContainsString('interface {{moduleName}}RepositoryInterface', $repoInterfaceContent);
        $this->assertStringContainsString('public function getAll()', $repoInterfaceContent);
        $this->assertStringContainsString('public function findById($id)', $repoInterfaceContent);
        $this->assertStringContainsString('public function create(array $data)', $repoInterfaceContent);
        $this->assertStringContainsString('public function update($id, array $data)', $repoInterfaceContent);
        $this->assertStringContainsString('public function delete($id)', $repoInterfaceContent);

        // Check repository implementation stub
        $repoContent = $this->files->get($this->stubsDirectory . '/repository.stub');

        $this->assertStringContainsString('class {{moduleName}}Repository implements {{moduleName}}RepositoryInterface', $repoContent);
        $this->assertStringContainsString('protected $model', $repoContent);
        $this->assertStringContainsString('public function __construct({{moduleName}} $model)', $repoContent);
    }

    /** @test */
    public function service_stubs_follow_pattern_correctly()
    {
        // Check service interface stub
        $serviceInterfaceContent = $this->files->get($this->stubsDirectory . '/service-interface.stub');

        $this->assertStringContainsString('interface {{moduleName}}ServiceInterface', $serviceInterfaceContent);
        $this->assertStringContainsString('public function getAll()', $serviceInterfaceContent);
        $this->assertStringContainsString('public function findById($id)', $serviceInterfaceContent);
        $this->assertStringContainsString('public function create(array $data)', $serviceInterfaceContent);
        $this->assertStringContainsString('public function update($id, array $data)', $serviceInterfaceContent);
        $this->assertStringContainsString('public function delete($id)', $serviceInterfaceContent);

        // Check service implementation stub
        $serviceContent = $this->files->get($this->stubsDirectory . '/service.stub');

        $this->assertStringContainsString('class {{moduleName}}Service implements {{moduleName}}ServiceInterface', $serviceContent);
        $this->assertStringContainsString('protected $repository', $serviceContent);
        $this->assertStringContainsString('public function __construct({{moduleName}}RepositoryInterface $repository)', $serviceContent);
    }

    /** @test */
    public function controller_stubs_follow_laravel_conventions()
    {
        // Check web controller stub
        $webControllerContent = $this->files->get($this->stubsDirectory . '/web-controller.stub');

        $this->assertStringContainsString('class {{moduleName}}Controller extends Controller', $webControllerContent);
        $this->assertStringContainsString('{{moduleName}}ServiceInterface', $webControllerContent);
        $this->assertStringContainsString('public function index()', $webControllerContent);
        $this->assertStringContainsString('public function create()', $webControllerContent);
        $this->assertStringContainsString('public function store(', $webControllerContent);
        $this->assertStringContainsString('public function show(', $webControllerContent);
        $this->assertStringContainsString('public function edit(', $webControllerContent);
        $this->assertStringContainsString('public function update(', $webControllerContent);
        $this->assertStringContainsString('public function destroy(', $webControllerContent);

        // Check API controller stub
        $apiControllerContent = $this->files->get($this->stubsDirectory . '/api-controller.stub');

        $this->assertStringContainsString('namespace {{namespace}}\{{moduleName}}\Http\Controllers\API', $apiControllerContent);
        $this->assertStringContainsString('class {{moduleName}}Controller extends Controller', $apiControllerContent);
        $this->assertStringContainsString('return response()->json', $apiControllerContent);
    }

    /** @test */
    public function route_stubs_follow_laravel_conventions()
    {
        // Check web routes stub
        $webRoutesContent = $this->files->get($this->stubsDirectory . '/web-routes.stub');

        $this->assertStringContainsString('use Illuminate\Support\Facades\Route', $webRoutesContent);
        $this->assertStringContainsString('Route::resource(\'{{moduleNameLower}}\', {{moduleName}}Controller::class)', $webRoutesContent);

        // Check API routes stub
        $apiRoutesContent = $this->files->get($this->stubsDirectory . '/api-routes.stub');

        $this->assertStringContainsString('use Illuminate\Support\Facades\Route', $apiRoutesContent);
        $this->assertStringContainsString('Route::apiResource(\'{{moduleNameLower}}\', {{moduleName}}Controller::class)', $apiRoutesContent);
    }

    /** @test */
    public function view_stubs_follow_laravel_conventions()
    {
        // Check index view stub
        $indexViewContent = $this->files->get($this->stubsDirectory . '/view-index.stub');

        $this->assertStringContainsString('@extends', $indexViewContent);
        $this->assertStringContainsString('@section', $indexViewContent);
        $this->assertStringContainsString('{{moduleName}}', $indexViewContent);

        // Check other view stubs
        $this->assertStringContainsString('form', $this->files->get($this->stubsDirectory . '/view-create.stub'));
        $this->assertStringContainsString('form', $this->files->get($this->stubsDirectory . '/view-edit.stub'));
        $this->assertStringContainsString('Details', $this->files->get($this->stubsDirectory . '/view-show.stub'));
    }

    /** @test */
    public function livewire_stubs_follow_livewire_conventions()
    {
        // Check Livewire table component
        $tableContent = $this->files->get($this->stubsDirectory . '/livewire-table.stub');

        $this->assertStringContainsString('namespace {{namespace}}\{{moduleName}}\Livewire', $tableContent);
        $this->assertStringContainsString('class {{moduleName}}Table extends Component', $tableContent);
        $this->assertStringContainsString('use WithPagination', $tableContent);
        $this->assertStringContainsString('public function render()', $tableContent);

        // Check Livewire form component
        $formContent = $this->files->get($this->stubsDirectory . '/livewire-form.stub');

        $this->assertStringContainsString('namespace {{namespace}}\{{moduleName}}\Livewire', $formContent);
        $this->assertStringContainsString('class {{moduleName}}Form extends Component', $formContent);
        $this->assertStringContainsString('public function save()', $formContent);
        $this->assertStringContainsString('protected $rules', $formContent);
    }

    /** @test */
    public function module_layout_stub_follows_modern_practices()
    {
        $layoutContent = $this->files->get($this->stubsDirectory . '/module-layout.stub');

        $this->assertStringContainsString('<!DOCTYPE html>', $layoutContent);
        $this->assertStringContainsString('<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}">', $layoutContent);
        $this->assertStringContainsString('@livewireStyles', $layoutContent);
        $this->assertStringContainsString('@livewireScripts', $layoutContent);
        $this->assertStringContainsString('{{moduleName}}', $layoutContent);
        $this->assertStringContainsString('@yield(\'content\')', $layoutContent);
    }
}
