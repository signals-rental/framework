<?php

namespace App\Http\Controllers\Web;

use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OpportunityLineItemController extends Controller
{
    public function destroy(
        Request $request,
        Opportunity $opportunity,
        OpportunityItem $item,
    ): JsonResponse {
        // Authorization lives in RemoveOpportunityItem (Gate::authorize there is the
        // single authority); the controller stays thin. IDOR scoping (the item
        // belongs to this opportunity) is checked before any state-revealing 422.
        abort_unless($item->opportunity_id === $opportunity->id, Response::HTTP_NOT_FOUND);

        if ($opportunity->statusEnum()->isClosed()) {
            throw ValidationException::withMessages([
                'opportunity' => 'This opportunity is closed and its line items cannot be edited.',
            ]);
        }

        if ($opportunity->pricingFrozen()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be removed while pricing is frozen.',
            ]);
        }

        // scope=section is kept for URL compatibility; group rows always cascade
        // their subtree via RemoveOpportunityItem (deepest-first).
        (new RemoveOpportunityItem)($item);

        return response()->json(['ok' => true]);
    }
}
