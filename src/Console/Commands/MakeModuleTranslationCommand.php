<?php

namespace NgarakDev\Modularization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeModuleTranslationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-translation 
                            {module : The name of the module}
                            {--languages=* : Languages to generate (default: en,es,fr,de)}
                            {--force : Overwrite existing translation files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create translation files for a module';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $moduleName = $this->argument('module');
        $languages = $this->option('languages');
        $force = $this->option('force');

        // Set default languages if none provided
        if (empty($languages)) {
            $languages = ['en', 'es', 'fr', 'de'];
        }

        $modulesPath = base_path(config('modularization.modules_path', 'modules'));
        $modulePath = $modulesPath . '/' . $moduleName;

        // Check if module exists
        if (!$this->files->isDirectory($modulePath)) {
            $this->error("Module [{$moduleName}] does not exist!");
            return 1;
        }

        // Create translation files
        $this->createTranslationFiles($moduleName, $modulePath, $languages, $force);

        $this->info("Translation files created successfully for module [{$moduleName}]");
        return 0;
    }

    /**
     * Create translation files for the module.
     *
     * @param string $name Module name
     * @param string $path Module path
     * @param array $languages Languages
     * @param bool $force Force overwrite
     * @return void
     */
    protected function createTranslationFiles($name, $path, $languages, $force)
    {
        $langPath = $path . '/Resources/lang';

        if (!$this->files->isDirectory($langPath)) {
            $this->files->makeDirectory($langPath, 0755, true);
        }

        $moduleLowerName = strtolower($name);

        foreach ($languages as $lang) {
            $langDir = $langPath . '/' . $lang;

            if (!$this->files->isDirectory($langDir)) {
                $this->files->makeDirectory($langDir, 0755, true);
            }

            // Create general.php for common translations
            $generalPath = $langDir . '/general.php';
            if (!$this->files->exists($generalPath) || $force) {
                $generalContent = $this->getTranslationGeneralStub([
                    '{{moduleName}}' => $name,
                    '{{language}}' => $lang,
                ]);

                $this->files->put($generalPath, $generalContent);
                $this->info("Created general translations for language: {$lang}");
            } else {
                $this->warn("Skipping general translations for language {$lang} (already exists)");
            }

            // Create validation.php for form validation messages
            $validationPath = $langDir . '/validation.php';
            if (!$this->files->exists($validationPath) || $force) {
                $validationContent = $this->getTranslationValidationStub([
                    '{{moduleName}}' => $name,
                    '{{language}}' => $lang,
                ]);

                $this->files->put($validationPath, $validationContent);
                $this->info("Created validation translations for language: {$lang}");
            } else {
                $this->warn("Skipping validation translations for language {$lang} (already exists)");
            }

            // Create module-specific translations
            $modulePath = $langDir . '/' . $moduleLowerName . '.php';
            if (!$this->files->exists($modulePath) || $force) {
                $moduleContent = $this->getTranslationModuleStub([
                    '{{moduleName}}' => $name,
                    '{{moduleNameLower}}' => $moduleLowerName,
                    '{{language}}' => $lang,
                ]);

                $this->files->put($modulePath, $moduleContent);
                $this->info("Created module-specific translations for language: {$lang}");
            } else {
                $this->warn("Skipping module-specific translations for language {$lang} (already exists)");
            }
        }
    }

    /**
     * Get the general translation stub.
     *
     * @param array $replacements Replacements
     * @return string
     */
    protected function getTranslationGeneralStub($replacements = [])
    {
        $stub = <<<'EOT'
<?php

return [
    'created' => 'Created successfully',
    'updated' => 'Updated successfully',
    'deleted' => 'Deleted successfully',
    'not_found' => 'Not found',
    'error' => 'An error occurred',
    'actions' => 'Actions',
    'create' => 'Create',
    'edit' => 'Edit',
    'update' => 'Update',
    'delete' => 'Delete',
    'cancel' => 'Cancel',
    'save' => 'Save',
    'back' => 'Back',
    'confirm' => 'Confirm',
    'confirm_delete' => 'Are you sure you want to delete this?',
    'yes' => 'Yes',
    'no' => 'No',
];
EOT;

        foreach ($replacements as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }

    /**
     * Get the validation translation stub.
     *
     * @param array $replacements Replacements
     * @return string
     */
    protected function getTranslationValidationStub($replacements = [])
    {
        $stub = <<<'EOT'
<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be a string.',
    'numeric' => 'The :attribute must be a number.',
    'email' => 'The :attribute must be a valid email address.',
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
        'numeric' => 'The :attribute must be at least :min.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'max' => [
        'string' => 'The :attribute must not exceed :max characters.',
        'numeric' => 'The :attribute must not exceed :max.',
        'array' => 'The :attribute must not have more than :max items.',
    ],
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'date' => 'The :attribute is not a valid date.',
];
EOT;

        foreach ($replacements as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }

    /**
     * Get the module-specific translation stub.
     *
     * @param array $replacements Replacements
     * @return string
     */
    protected function getTranslationModuleStub($replacements = [])
    {
        $moduleNames = [
            'en' => '{{moduleName}}',
            'es' => '{{moduleName}}',
            'fr' => '{{moduleName}}',
            'de' => '{{moduleName}}',
        ];

        $createTexts = [
            'en' => 'Create {{moduleName}}',
            'es' => 'Crear {{moduleName}}',
            'fr' => 'Créer {{moduleName}}',
            'de' => 'Erstellen {{moduleName}}',
        ];

        $editTexts = [
            'en' => 'Edit {{moduleName}}',
            'es' => 'Editar {{moduleName}}',
            'fr' => 'Modifier {{moduleName}}',
            'de' => 'Bearbeiten {{moduleName}}',
        ];

        $language = $replacements['{{language}}'] ?? 'en';

        $stub = <<<'EOT'
<?php

return [
    '{{moduleNameLower}}' => '{{translatedName}}',
    'all_{{moduleNameLower}}s' => 'All {{translatedName}}s',
    'create_{{moduleNameLower}}' => '{{createText}}',
    'edit_{{moduleNameLower}}' => '{{editText}}',
    'details' => 'Details',
    'name' => 'Name',
    'description' => 'Description',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
];
EOT;

        // Replace with language-specific text
        $translatedName = $moduleNames[$language] ?? $moduleNames['en'];
        $createText = $createTexts[$language] ?? $createTexts['en'];
        $editText = $editTexts[$language] ?? $editTexts['en'];

        $stub = str_replace('{{translatedName}}', $translatedName, $stub);
        $stub = str_replace('{{createText}}', $createText, $stub);
        $stub = str_replace('{{editText}}', $editText, $stub);

        foreach ($replacements as $search => $replace) {
            $stub = str_replace($search, $replace, $stub);
        }

        return $stub;
    }
}
