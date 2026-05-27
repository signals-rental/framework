<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rates\CreateRateDefinition;
use App\Actions\Rates\DeleteRateDefinition;
use App\Actions\Rates\DuplicateRateDefinition;
use App\Actions\Rates\UpdateRateDefinition;
use App\Data\Rates\CreateRateDefinitionData;
use App\Data\Rates\RateDefinitionData;
use App\Data\Rates\UpdateRateDefinitionData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\RateDefinition;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateDefinitionController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'calculation_strategy',
        'base_period',
        'is_preset',
        'preset_slug',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'calculation_strategy',
        'base_period',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'clonedFrom',
        'productRates',
    ];

    protected function modelClass(): string
    {
        return RateDefinition::class;
    }

    protected function responseDataClass(): string
    {
        return RateDefinitionData::class;
    }

    protected function createDataClass(): string
    {
        return CreateRateDefinitionData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateRateDefinitionData::class;
    }

    protected function createActionClass(): string
    {
        return CreateRateDefinition::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateRateDefinition::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteRateDefinition::class;
    }

    protected function singularKey(): string
    {
        return 'rate_definition';
    }

    protected function pluralKey(): string
    {
        return 'rate_definitions';
    }

    protected function entityType(): string
    {
        return 'rate_definitions';
    }

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    protected function permissions(): array
    {
        return ['view' => 'rates.view', 'create' => 'rates.create', 'edit' => 'rates.edit', 'delete' => 'rates.delete'];
    }

    /**
     * @return array{read: string, write: string}
     */
    protected function abilities(): array
    {
        return ['read' => 'rates:read', 'write' => 'rates:write'];
    }

    /**
     * List rate definitions with filtering, sorting, and pagination.
     *
     * Filter with `q[calculation_strategy_eq]`, `q[base_period_eq]`, `q[is_preset_true]`.
     */
    #[ApiResponse(200, 'Paginated rate definition list', type: 'array{rate_definitions: list<array{id: int, name: string, description: string|null, calculation_strategy: string, calculation_strategy_name: string, base_period: string|null, base_period_name: string|null, enabled_modifiers: list<string>, strategy_config: array<string, mixed>, modifier_configs: array<string, mixed>, is_preset: bool, preset_slug: string|null, cloned_from_id: int|null, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single rate definition.
     */
    #[ApiResponse(200, 'Rate definition details', type: 'array{rate_definition: array{id: int, name: string, description: string|null, calculation_strategy: string, calculation_strategy_name: string, base_period: string|null, base_period_name: string|null, enabled_modifiers: list<string>, strategy_config: array<string, mixed>, modifier_configs: array<string, mixed>, is_preset: bool, preset_slug: string|null, cloned_from_id: int|null, created_at: string, updated_at: string}}')]
    public function show(Request $request, RateDefinition $rateDefinition): JsonResponse
    {
        return $this->resourceShow($request, $rateDefinition);
    }

    /**
     * Create a new rate definition.
     */
    #[ApiResponse(201, 'Rate definition created', type: 'array{rate_definition: array{id: int, name: string, calculation_strategy: string, is_preset: bool, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing rate definition.
     */
    #[ApiResponse(200, 'Rate definition updated', type: 'array{rate_definition: array{id: int, name: string, calculation_strategy: string, is_preset: bool, created_at: string, updated_at: string}}')]
    public function update(Request $request, RateDefinition $rateDefinition): JsonResponse
    {
        return $this->resourceUpdate($request, $rateDefinition);
    }

    /**
     * Delete a rate definition.
     */
    #[ApiResponse(204, 'Rate definition deleted')]
    public function destroy(RateDefinition $rateDefinition): JsonResponse
    {
        return $this->resourceDestroy($rateDefinition);
    }

    /**
     * Duplicate a rate definition into a new editable copy.
     */
    #[ApiResponse(201, 'Rate definition duplicated', type: 'array{rate_definition: array{id: int, name: string, calculation_strategy: string, is_preset: bool, cloned_from_id: int|null, created_at: string, updated_at: string}}')]
    public function duplicate(RateDefinition $rateDefinition): JsonResponse
    {
        $this->authorizeApi($this->permissions()['create'], $this->abilities()['write']);

        $result = (new DuplicateRateDefinition)($rateDefinition);

        return $this->respondWith($result->toArray(), $this->singularKey(), Response::HTTP_CREATED);
    }
}
