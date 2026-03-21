<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TaxClasses\CreateProductTaxClass;
use App\Actions\TaxClasses\DeleteProductTaxClass;
use App\Actions\TaxClasses\UpdateProductTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\TaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\ProductTaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductTaxClassController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return ProductTaxClass::class;
    }

    protected function responseDataClass(): string
    {
        return TaxClassData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxClassData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxClassData::class;
    }

    protected function createActionClass(): string
    {
        return CreateProductTaxClass::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateProductTaxClass::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteProductTaxClass::class;
    }

    protected function singularKey(): string
    {
        return 'product_tax_class';
    }

    protected function pluralKey(): string
    {
        return 'product_tax_classes';
    }

    protected function entityType(): string
    {
        return 'product_tax_classes';
    }

    protected function permissions(): array
    {
        return ['view' => 'tax-classes.view', 'create' => 'tax-classes.manage', 'edit' => 'tax-classes.manage', 'delete' => 'tax-classes.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'tax-classes:read', 'write' => 'tax-classes:write'];
    }

    /**
     * List product tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single product tax class.
     */
    public function show(Request $request, ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceShow($request, $productTaxClass);
    }

    /**
     * Create a product tax class.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update a product tax class.
     */
    public function update(Request $request, ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceUpdate($request, $productTaxClass);
    }

    /**
     * Delete a product tax class.
     */
    public function destroy(ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceDestroy($productTaxClass);
    }
}
