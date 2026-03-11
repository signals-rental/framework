<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TaxClasses\CreateOrganisationTaxClass;
use App\Actions\TaxClasses\DeleteOrganisationTaxClass;
use App\Actions\TaxClasses\UpdateOrganisationTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\TaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\OrganisationTaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganisationTaxClassController extends Controller
{
    use FiltersQueries;

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

    /**
     * List organisation tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        $query = OrganisationTaxClass::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $classes = $paginator->getCollection()->map(
            fn (OrganisationTaxClass $taxClass): array => TaxClassData::fromModel($taxClass)->toArray()
        )->all();

        return $this->respondWithCollection($classes, 'organisation_tax_classes', $paginator);
    }

    /**
     * Show a single organisation tax class.
     */
    public function show(OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.view', 'tax-classes:read');

        return $this->respondWith(
            TaxClassData::fromModel($organisationTaxClass)->toArray(),
            'organisation_tax_class',
        );
    }

    /**
     * Create an organisation tax class.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(CreateTaxClassData::rules());
        $dto = CreateTaxClassData::from($validated);

        $result = (new CreateOrganisationTaxClass)($dto);

        return $this->respondWith(
            $result->toArray(),
            'organisation_tax_class',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an organisation tax class.
     */
    public function update(Request $request, OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        $validated = $request->validate(UpdateTaxClassData::rules());
        $dto = UpdateTaxClassData::from($validated);

        $result = (new UpdateOrganisationTaxClass)($organisationTaxClass, $dto);

        return $this->respondWith(
            $result->toArray(),
            'organisation_tax_class',
        );
    }

    /**
     * Delete an organisation tax class.
     */
    public function destroy(OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        $this->authorizeApi('tax-classes.manage', 'tax-classes:write');

        (new DeleteOrganisationTaxClass)($organisationTaxClass);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
