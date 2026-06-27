<?php

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\User;
use App\Services\DocsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

describe('DocsController::index with empty navigation', function () {
    it('aborts 404 when there are no documentation sections', function () {
        // DocsService returns navigation with no sections — the index redirect has
        // nothing to point at, so it must 404 (line 26).
        $this->mock(DocsService::class, function ($mock): void {
            $mock->shouldReceive('getNavigation')->andReturn(['sections' => []]);
        });

        get(route('docs.index'))->assertNotFound();
    });
});

describe('OpportunityLineItemController::destroy on a closed opportunity', function () {
    it('rejects removing a line item when the opportunity is closed', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $user = User::factory()->owner()->create();

        // A closed (QuotationLost) opportunity: its line items cannot be edited, so
        // destroy throws a ValidationException (lines 27-29) before any removal.
        $opportunity = Opportunity::factory()->quotation()->create([
            'status' => OpportunityStatus::QuotationLost->statusValue(),
        ]);
        $item = OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
        ]);

        actingAs($user)
            ->deleteJson(route('opportunities.items.destroy', [$opportunity, $item]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('opportunity');

        // The item is untouched.
        expect(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
    });
});
