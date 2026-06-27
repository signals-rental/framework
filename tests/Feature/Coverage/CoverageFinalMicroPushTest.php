<?php

use App\Livewire\Components\DataTable;
use App\Models\Member;
use App\Models\User;
use App\Verbs\Events\Opportunities\Concerns\GuardsOpportunityLifecycle;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * Test double exposing {@see GuardsOpportunityLifecycle} helpers for null-projection guards.
 */
class GuardsOpportunityLifecycleCoverageDouble
{
    use GuardsOpportunityLifecycle;

    public function hasActiveItem(int $stateId): bool
    {
        return $this->opportunityHasActiveItem($stateId);
    }

    public function hasStockOut(int $stateId): bool
    {
        return $this->opportunityHasStockOut($stateId);
    }

    public function hasUnreturnedAssets(int $stateId): bool
    {
        return $this->opportunityHasUnreturnedAssets($stateId);
    }
}

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('returns false from lifecycle guards when no opportunity row exists for the state id', function () {
    $guard = new GuardsOpportunityLifecycleCoverageDouble;
    $missingStateId = 9_999_999_999;

    expect($guard->hasActiveItem($missingStateId))->toBeFalse()
        ->and($guard->hasStockOut($missingStateId))->toBeFalse()
        ->and($guard->hasUnreturnedAssets($missingStateId))->toBeFalse();
});

it('returns an empty column definition when the key is not present in the table config', function () {
    $component = Livewire::test(DataTable::class, [
        'columns' => [
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ],
        'model' => Member::class,
    ]);

    $method = new ReflectionMethod(DataTable::class, 'getColumnDefinition');
    $method->setAccessible(true);

    expect($method->invoke($component->instance(), 'missing_column'))->toBe([]);
});
