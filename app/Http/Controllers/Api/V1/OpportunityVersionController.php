<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\ActivateVersion;
use App\Actions\Opportunities\ChangeVersionLabel;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeclineVersion;
use App\Actions\Opportunities\DeleteVersion;
use App\Actions\Opportunities\DiffVersions;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\ChangeVersionLabelData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Http\Controllers\Api\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API surface for quote versions — a SUB-RESOURCE of opportunities
 * (opportunity-lifecycle.md §8). There is no top-level versions endpoint:
 * versions are always addressed under their opportunity.
 *
 * Reads hit the `opportunity_versions` projection directly (zero replay cost).
 * Every write delegates to a lifecycle action, which fires the Verbs version
 * event and dual-writes the projection in one atomic transaction. Authorisation
 * reuses the opportunities permissions/abilities — a version is an aspect of an
 * opportunity, not a standalone resource.
 */
class OpportunityVersionController extends Controller
{
    /**
     * List an opportunity's quote versions (oldest first).
     */
    #[ApiResponse(200, 'Version list')]
    public function index(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');

        $withItems = $this->wantsItems($request);

        $versions = $opportunity->versions()
            ->when($withItems, fn ($query) => $query->with('items'))
            ->get()
            ->map(fn (OpportunityVersion $version): array => OpportunityVersionData::fromModel($version)->toArray())
            ->all();

        return $this->respondWithCollection($versions, 'versions');
    }

    /**
     * Show a single quote version.
     */
    #[ApiResponse(200, 'Version details')]
    public function show(Request $request, Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        if ($this->wantsItems($request)) {
            $version->load('items');
        }

        return $this->respondWith(OpportunityVersionData::fromModel($version)->toArray(), 'version');
    }

    /**
     * Create a quote version (a revision or an alternative).
     *
     * The source version's line items are cloned into the new version; a revision
     * supersedes its parent, an alternative coexists. Valid only while the
     * opportunity is a Quotation, within the configured version/alternative caps —
     * a violation yields a 422.
     */
    #[ApiResponse(201, 'Version created')]
    public function store(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');

        $data = CreateVersionData::from($request->validate(CreateVersionData::rules()));

        $result = (new CreateVersion)($opportunity, $data);

        return $this->respondWith($result->toArray(), 'version', Response::HTTP_CREATED);
    }

    /**
     * Make a version the active version of its opportunity.
     *
     * The opportunity's totals and line-item scope follow the active version, and
     * availability demand swaps to it.
     */
    #[ApiResponse(200, 'Version activated')]
    public function activate(Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $result = (new ActivateVersion)($version);

        return $this->respondWith($result->toArray(), 'version');
    }

    /**
     * Mark a version as Sent to the customer.
     */
    #[ApiResponse(200, 'Version sent')]
    public function send(Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $result = (new SendVersion)($version);

        return $this->respondWith($result->toArray(), 'version');
    }

    /**
     * Mark a version as Accepted by the customer.
     */
    #[ApiResponse(200, 'Version accepted')]
    public function accept(Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $result = (new AcceptVersion)($version);

        return $this->respondWith($result->toArray(), 'version');
    }

    /**
     * Mark a version as Declined by the customer.
     */
    #[ApiResponse(200, 'Version declined')]
    public function decline(Request $request, Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $result = (new DeclineVersion)($version, $validated['reason'] ?? null);

        return $this->respondWith($result->toArray(), 'version');
    }

    /**
     * Rename a version's label.
     */
    #[ApiResponse(200, 'Version relabelled')]
    public function update(Request $request, Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $data = ChangeVersionLabelData::from($request->validate(ChangeVersionLabelData::rules()));

        $result = (new ChangeVersionLabel)($version, $data);

        return $this->respondWith($result->toArray(), 'version');
    }

    /**
     * Diff two versions of the same opportunity.
     *
     * Returns the item-level content delta (added / removed / changed lines) and
     * the net change in total value, computed on-demand from the projections.
     */
    #[ApiResponse(200, 'Version diff')]
    public function diff(Request $request, Opportunity $opportunity, OpportunityVersion $from, OpportunityVersion $to): JsonResponse
    {
        $this->authorizeApi('opportunities.view', 'opportunities:read');
        $this->assertVersionBelongsToOpportunity($from, $opportunity);
        $this->assertVersionBelongsToOpportunity($to, $opportunity);

        $result = (new DiffVersions)($from, $to);

        return $this->respondWith($result->toArray(), 'diff');
    }

    /**
     * Delete a (non-active, non-only) version while the opportunity is a Quotation.
     */
    #[ApiResponse(204, 'Version deleted')]
    public function destroy(Request $request, Opportunity $opportunity, OpportunityVersion $version): JsonResponse
    {
        $this->authorizeApi('opportunities.edit', 'opportunities:write');
        $this->assertVersionBelongsToOpportunity($version, $opportunity);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        (new DeleteVersion)($version, $validated['reason'] ?? null);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Guard that a version belongs to the bound opportunity (else 404).
     */
    private function assertVersionBelongsToOpportunity(OpportunityVersion $version, Opportunity $opportunity): void
    {
        abort_unless($version->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Whether the request asked for line items to be embedded (`?include=items`).
     */
    private function wantsItems(Request $request): bool
    {
        $include = (string) $request->query('include', '');

        return in_array('items', array_map('trim', explode(',', $include)), true);
    }
}
