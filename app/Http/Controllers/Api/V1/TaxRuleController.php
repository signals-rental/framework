<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tax\CreateTaxRule;
use App\Actions\Tax\DeleteTaxRule;
use App\Actions\Tax\UpdateTaxRule;
use App\Data\Tax\CreateTaxRuleData;
use App\Data\Tax\TaxRuleData;
use App\Data\Tax\UpdateTaxRuleData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaxRuleController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'organisation_tax_class_id',
        'product_tax_class_id',
        'tax_rate_id',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'priority',
        'created_at',
    ];

    /**
     * List tax rules.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        $query = TaxRule::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $rules = $paginator->getCollection()->map(
            fn (TaxRule $taxRule): array => TaxRuleData::fromModel($taxRule)->toArray()
        )->all();

        return $this->respondWithCollection($rules, 'tax_rules', $paginator);
    }

    /**
     * Show a single tax rule.
     */
    public function show(TaxRule $taxRule): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        return $this->respondWith(
            TaxRuleData::fromModel($taxRule)->toArray(),
            'tax_rule',
        );
    }

    /**
     * Create a tax rule.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(CreateTaxRuleData::rules());
        $dto = CreateTaxRuleData::from($validated);

        $result = (new CreateTaxRule)($dto);

        return $this->respondWith(
            $result->toArray(),
            'tax_rule',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a tax rule.
     */
    public function update(Request $request, TaxRule $taxRule): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(UpdateTaxRuleData::rules());
        $dto = UpdateTaxRuleData::from($validated);

        $result = (new UpdateTaxRule)($taxRule, $dto);

        return $this->respondWith(
            $result->toArray(),
            'tax_rule',
        );
    }

    /**
     * Delete a tax rule.
     */
    public function destroy(TaxRule $taxRule): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        (new DeleteTaxRule)($taxRule);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
