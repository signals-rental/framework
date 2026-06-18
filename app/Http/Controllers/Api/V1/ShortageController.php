<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Shortages\AcknowledgeOpportunityShortages;
use App\Actions\Shortages\ApplyShortageResolution;
use App\Actions\Shortages\DetectOpportunityShortages;
use App\Data\Shortages\AcknowledgeShortageData;
use App\Data\Shortages\ApplyResolutionData;
use App\Data\Shortages\ShortageData;
use App\Data\Shortages\ShortageResolutionData;
use App\Http\Controllers\Api\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Shortages\ShortageConfirmationGate;
use App\Services\Shortages\ShortageDetector;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\Shortage;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shortage detection and resolution surface
 * (shortage-resolution-sub-hires.md §2, §3, §7).
 *
 * Reads compute shortages live from the availability engine (never persisted);
 * writes apply resolver options and record acknowledgements through the shortage
 * actions. Authorisation is the `shortages.view`/`shortages.resolve` Gate
 * permissions plus the `shortages:read`/`shortages:write` token abilities.
 */
class ShortageController extends Controller
{
    /**
     * Detect the current shortages on an opportunity.
     *
     * Returns one entry per short line item, each with its shortfall and
     * remaining (unresolved) shortfall.
     */
    #[ApiResponse(200, 'Opportunity shortages', type: 'array{shortages: list<array{opportunity_item_id: int, opportunity_id: int, product_id: int, product_name: string, store_id: int, requested_quantity: int, available_quantity: int, shortfall: int, remaining_shortfall: int, tracking_type: string, starts_at: string, ends_at: string, is_critical: bool}>, meta: array{total: int}}')]
    public function index(Opportunity $opportunity): JsonResponse
    {
        $this->authorizeShortage('shortages.view', 'shortages:read');

        $shortages = (new DetectOpportunityShortages(app(ShortageDetector::class)))($opportunity);

        return $this->respondWithCollection(
            $shortages->map(static fn (Shortage $shortage): array => ShortageData::fromShortage($shortage)->toArray())->all(),
            'shortages',
        );
    }

    /**
     * List the resolver options applicable to a line item's current shortage.
     *
     * Each resolver contributes zero or more concrete options the user can apply.
     */
    #[ApiResponse(200, 'Applicable resolver options', type: 'array{resolvers: list<array{resolver_key: string, name: string, priority: int, auto_executable: bool, options: list<array{resolver_key: string, type: string, label: string, description: string, quantity_resolved: int, is_partial: bool, auto_executable: bool, estimated_cost: int|null, estimated_lead_time: int|null, requires_confirmation: bool, metadata: array<string, mixed>}>}>}')]
    public function resolvers(Opportunity $opportunity, OpportunityItem $item): JsonResponse
    {
        $this->authorizeShortage('shortages.view', 'shortages:read');

        $this->assertItemBelongsToOpportunity($item, $opportunity);

        $shortage = app(ShortageDetector::class)->forItem($item, $opportunity);

        if ($shortage === null) {
            return response()->json(['resolvers' => []]);
        }

        $registry = app(ShortageResolverRegistry::class);

        $resolvers = array_map(
            static fn ($resolver): array => [
                'resolver_key' => $resolver->key(),
                'name' => $resolver->name(),
                'priority' => $resolver->priority(),
                'auto_executable' => $resolver->isAutoExecutable(),
                'options' => array_map(
                    static fn ($option): array => $option->toArray(),
                    $resolver->getOptions($shortage),
                ),
            ],
            $registry->applicableTo($shortage),
        );

        return response()->json(['resolvers' => $resolvers]);
    }

    /**
     * Apply a resolver option to a line item's shortage, recording a resolution.
     */
    #[ApiResponse(201, 'Resolution applied', type: 'array{resolution: array{id: int, resolver_key: string, resolution_type: string, status: string, status_label: string, quantity_resolved: int, cost: int|null}, status: string, message: string, requires_followup: bool}')]
    public function resolve(Request $request): JsonResponse
    {
        $this->authorizeShortage('shortages.resolve', 'shortages:write');

        $data = ApplyResolutionData::from($request->validate(ApplyResolutionData::rules()));

        $result = (new ApplyShortageResolution(
            app(ShortageDetector::class),
            app(ShortageResolverRegistry::class),
        ))($data);

        $payload = [
            'resolution' => $result->resolution !== null
                ? ShortageResolutionData::fromModel($result->resolution->load('items'))->toArray()
                : null,
            'status' => $result->status->value,
            'message' => $result->message,
            'requires_followup' => $result->requiresFollowup,
        ];

        return response()->json($payload, Response::HTTP_CREATED);
    }

    /**
     * Acknowledge an opportunity's shortages, recording the gate acknowledgement
     * with a frozen snapshot.
     */
    #[ApiResponse(201, 'Shortages acknowledged', type: 'array{acknowledgement: array{id: int, opportunity_id: int, policy_at_time: string, permission_used: bool, acknowledged_at: string}}')]
    public function acknowledge(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeShortage('shortages.resolve', 'shortages:write');

        $data = AcknowledgeShortageData::from($request->validate(AcknowledgeShortageData::rules()));

        $acknowledgement = (new AcknowledgeOpportunityShortages(
            app(ShortageConfirmationGate::class),
        ))($opportunity, $data);

        return response()->json([
            'acknowledgement' => [
                'id' => $acknowledgement->id,
                'opportunity_id' => $acknowledgement->opportunity_id,
                'policy_at_time' => $acknowledgement->policy_at_time->value,
                'permission_used' => $acknowledgement->permission_used,
                'acknowledged_at' => $acknowledgement->acknowledged_at->utc()->format('Y-m-d\TH:i:s.v\Z'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Guard that a line item belongs to the bound opportunity (else 404).
     */
    private function assertItemBelongsToOpportunity(OpportunityItem $item, Opportunity $opportunity): void
    {
        abort_unless($item->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);
    }

    /**
     * Authorise a shortage read/write: the Gate permission plus the token ability.
     */
    private function authorizeShortage(string $permission, string $ability): void
    {
        Gate::authorize($permission);

        /** @var PersonalAccessToken|null $token */
        $token = request()->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability)) {
            abort(Response::HTTP_FORBIDDEN, "Token does not have the required ability: {$ability}");
        }
    }
}
