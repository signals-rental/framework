<?php

namespace App\Livewire\Dashboard;

use App\Enums\OpportunityState;
use App\Models\Opportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Dashboard widget summarising the opportunity pipeline at-a-glance.
 *
 * Renders four cheap aggregate stats — open quotations, live orders, orders
 * starting soon, and opportunities flagged with a shortage — each linking
 * through to the filtered Index. The whole widget is gated on
 * `opportunities.access`; users without it see nothing.
 */
class OpportunityPipeline extends Component
{
    /**
     * How many days ahead counts as "due to dispatch soon" for an order.
     */
    public int $dueSoonDays = 7;

    /**
     * Build the four pipeline stat counts in a small number of cheap aggregate
     * queries (no N+1, no row hydration).
     *
     * @return array{quotations: int, orders: int, due_soon: int, shortages: int}
     */
    public function counts(): array
    {
        $quotations = Opportunity::query()
            ->ofState(OpportunityState::Quotation)
            ->count();

        // The orders and due-soon counts share the same Order-state base query; build
        // it once and clone for each derived count.
        $orderBase = Opportunity::query()->ofState(OpportunityState::Order);

        $orders = (clone $orderBase)->count();

        $dueSoon = (clone $orderBase)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [Carbon::now(), Carbon::now()->addDays($this->dueSoonDays)])
            ->count();

        $shortages = Opportunity::query()
            ->where('has_shortage', true)
            ->count();

        return [
            'quotations' => $quotations,
            'orders' => $orders,
            'due_soon' => $dueSoon,
            'shortages' => $shortages,
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.opportunity-pipeline', [
            'counts' => $this->counts(),
            'dueSoonDays' => $this->dueSoonDays,
            'canAccess' => Gate::allows('opportunities.access'),
        ]);
    }
}
