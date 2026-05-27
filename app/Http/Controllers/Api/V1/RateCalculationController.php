<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Rates\CalculateRateData;
use App\Data\Rates\RateBreakdownData;
use App\Enums\BasePeriod;
use App\Http\Controllers\Api\Controller;
use App\Models\Product;
use App\Services\RateEngine\RateCalculator;
use App\Services\RateEngine\RateResolver;
use App\ValueObjects\CalculationContext;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RateCalculationController extends Controller
{
    /**
     * Calculate the rate breakdown for a product over a rental window.
     *
     * Resolves the highest-priority product rate for the given store, transaction
     * type and start date, then runs the rate engine. When no rate is configured
     * the response falls back to a zero-priced breakdown spanning the period
     * (`meta.resolved` is false), since the product carries no standalone price.
     */
    #[ApiResponse(200, 'Rate breakdown', type: 'array{rate_breakdown: array{currency: string, unit_price: string, units: int, unit_label: string, per_unit_subtotal: string, quantity: int, total: string, line_items: list<array{period_from: int, period_to: int, multiplier: string, unit_price: string, line_total: string}>, applied_modifiers: list<array{key: string, label: string, description: string, before: string, after: string}>}, meta: array{resolved: bool, rate_definition_id: int|null, product_rate_id: int|null}}')]
    public function calculate(Request $request, Product $product): JsonResponse
    {
        $this->authorizeCalculation($request);

        $validated = $request->validate(CalculateRateData::rules());
        $data = CalculateRateData::from($validated);

        $start = Carbon::parse($data->start);
        $end = Carbon::parse($data->end);

        $rate = app(RateResolver::class)->resolve($product, $data->transaction_type, $data->store_id, $start);
        $definition = $rate?->rateDefinition;

        if ($rate !== null && $definition !== null) {
            $context = new CalculationContext(
                unitPriceMinor: $rate->price,
                currency: $rate->currency,
                start: $start,
                end: $end,
                quantity: $data->quantity,
                basePeriod: $definition->base_period,
                strategyConfig: $definition->strategy_config ?? [],
                transactionType: $data->transaction_type,
                storeId: $data->store_id,
            );

            $breakdown = app(RateCalculator::class)->calculate(
                $context,
                $definition->calculation_strategy->value,
                $definition->enabled_modifiers ?? [],
                $definition->modifier_configs ?? [],
            );

            $meta = ['resolved' => true, 'rate_definition_id' => $definition->id, 'product_rate_id' => $rate->id];
        } else {
            $context = new CalculationContext(
                unitPriceMinor: 0,
                currency: (string) (settings('company.base_currency') ?? 'GBP'),
                start: $start,
                end: $end,
                quantity: $data->quantity,
                basePeriod: BasePeriod::Daily,
                strategyConfig: [],
                transactionType: $data->transaction_type,
                storeId: $data->store_id,
            );

            $breakdown = app(RateCalculator::class)->calculate($context, 'period');

            $meta = ['resolved' => false, 'rate_definition_id' => null, 'product_rate_id' => null];
        }

        return response()->json([
            'rate_breakdown' => RateBreakdownData::fromBreakdown($breakdown)->toArray(),
            'meta' => $meta,
        ]);
    }

    /**
     * Authorize a calculation: the user needs rate or product view access, and a
     * token (if used) must carry a matching read ability.
     */
    private function authorizeCalculation(Request $request): void
    {
        abort_unless(Gate::any(['rates.view', 'products.view']), Response::HTTP_FORBIDDEN);

        /** @var PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! ($token->can('rates:read') || $token->can('products:read'))) {
            abort(Response::HTTP_FORBIDDEN, 'Token does not have the required ability: rates:read');
        }
    }
}
