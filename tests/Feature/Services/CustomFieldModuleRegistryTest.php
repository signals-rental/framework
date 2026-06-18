<?php

use App\Models\Traits\HasCustomFields;
use App\Services\CustomFieldModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Guards the single source of truth for the "which modules support custom fields"
 * list. It must stay in sync with both the models that use HasCustomFields (the true
 * capability) and the API controllers' $customFieldModule declarations, and must never
 * contain a phantom module that lacks a backing Eloquent model.
 */
beforeEach(function () {
    $this->registry = app(CustomFieldModuleRegistry::class);
});

/**
 * Every API controller that declares a $customFieldModule must appear in the
 * single-sourced module list — otherwise an API-filterable module would be
 * absent from the admin field-definition form.
 *
 * @return list<string>
 */
function controllerCustomFieldModules(): array
{
    $modules = [];

    foreach (glob(app_path('Http/Controllers/Api/V1/*.php')) as $file) {
        $class = 'App\\Http\\Controllers\\Api\\V1\\'.basename($file, '.php');

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->hasProperty('customFieldModule')) {
            continue;
        }

        $property = $reflection->getProperty('customFieldModule');
        $default = $property->getDefaultValue();

        if (is_string($default) && $default !== '') {
            $modules[] = $default;
        }
    }

    return array_values(array_unique($modules));
}

it('includes every API controller that declares a custom field module', function () {
    $controllerModules = controllerCustomFieldModules();

    expect($controllerModules)->not->toBeEmpty();

    foreach ($controllerModules as $module) {
        expect($this->registry->has($module))
            ->toBeTrue("API controller declares \$customFieldModule = '{$module}' but it is missing from the CustomFieldModuleRegistry.");
    }
});

it('has no phantom module without a backing model', function () {
    foreach ($this->registry->models() as $modelClass) {
        expect(class_exists($modelClass))
            ->toBeTrue("Registered custom-field module model '{$modelClass}' does not exist.");

        expect(is_subclass_of($modelClass, Model::class))
            ->toBeTrue("Registered custom-field module '{$modelClass}' is not an Eloquent model.");
    }

    // Invoice still has no backing model yet and must not be a custom-field module.
    // (Opportunity now has a backing Eloquent projection model and IS a module.)
    expect($this->registry->has('Invoice'))->toBeFalse('Invoice has no backing model yet and must not be a custom-field module.');
});

it('every registered model uses the HasCustomFields trait', function () {
    foreach ($this->registry->models() as $modelClass) {
        expect(in_array(HasCustomFields::class, class_uses_recursive($modelClass), true))
            ->toBeTrue("Registered custom-field module '{$modelClass}' must use the HasCustomFields trait.");
    }
});

it('module type keys match the runtime resolution key of each model', function () {
    $modules = $this->registry->modules();

    foreach ($this->registry->models() as $modelClass) {
        $instance = new $modelClass;
        $moduleType = $instance->customFieldModuleType();

        expect(array_key_exists($moduleType, $modules))
            ->toBeTrue("Model '{$modelClass}' resolves to module type '{$moduleType}' but it is missing from the registry keys.");
    }
});

it('contains the expected Phase-2 custom field modules with labels', function () {
    $modules = $this->registry->modules();

    expect($modules)->toMatchArray([
        'Member' => 'Member',
        'Product' => 'Product',
        'ProductGroup' => 'Product Group',
        'StockLevel' => 'Stock Level',
        'Activity' => 'Activity',
        'Store' => 'Store',
        'Opportunity' => 'Opportunity',
    ]);
});

it('exposes a human-readable label for every module', function () {
    foreach ($this->registry->modules() as $moduleType => $label) {
        expect($label)->toBeString();
        expect($label)->not->toBe('');
        // Labels are presentation strings, not raw class-basename PascalCase identifiers
        // when the model name is multi-word (e.g. ProductGroup => "Product Group").
        expect(Str::contains($label, '\\'))->toBeFalse();
    }
});
