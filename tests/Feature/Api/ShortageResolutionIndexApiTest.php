<?php

use App\Enums\ShortageResolutionStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();

    $this->opportunity = Opportunity::factory()->create();
    $this->item = OpportunityItem::factory()->create(['opportunity_id' => $this->opportunity->id]);
});

/**
 * Attach a resolution to the test opportunity via the per-item pivot.
 */
function resolutionFor(OpportunityItem $item, ShortageResolution $resolution): ShortageResolution
{
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $item->id,
        'quantity_allocated' => 2,
    ]);

    return $resolution;
}

describe('GET /api/v1/opportunities/{opportunity}/shortage_resolutions', function () {
    it('lists resolutions scoped to the opportunity with their items', function () {
        resolutionFor($this->item, ShortageResolution::factory()->create()); // confirmed
        resolutionFor($this->item, ShortageResolution::factory()->pending()->create());

        // A resolution on an UNRELATED opportunity item — must be excluded.
        $otherItem = OpportunityItem::factory()->create();
        resolutionFor($otherItem, ShortageResolution::factory()->create());

        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortage_resolutions")
            ->assertOk();

        expect($response->json('shortage_resolutions'))->toHaveCount(2)
            ->and($response->json('meta.total'))->toBe(2)
            ->and($response->json('shortage_resolutions.0.items'))->toHaveCount(1)
            ->and($response->json('shortage_resolutions.0.items.0.opportunity_item_id'))->toBe($this->item->id);
    });

    it('filters by status via Ransack', function () {
        resolutionFor($this->item, ShortageResolution::factory()->create()); // confirmed
        resolutionFor($this->item, ShortageResolution::factory()->cancelled()->create());

        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortage_resolutions?q[status_eq]=cancelled")
            ->assertOk();

        expect($response->json('shortage_resolutions'))->toHaveCount(1)
            ->and($response->json('shortage_resolutions.0.status'))->toBe(ShortageResolutionStatus::Cancelled->value);
    });

    it('paginates the resolutions', function () {
        foreach (range(1, 3) as $i) {
            resolutionFor($this->item, ShortageResolution::factory()->create());
        }

        $token = $this->owner->createToken('test', ['shortages:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortage_resolutions?per_page=2&page=1")
            ->assertOk();

        expect($response->json('shortage_resolutions'))->toHaveCount(2)
            ->and($response->json('meta.total'))->toBe(3)
            ->and($response->json('meta.per_page'))->toBe(2)
            ->and($response->json('meta.page'))->toBe(1);
    });

    it('requires the shortages:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortage_resolutions")
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/opportunities/{$this->opportunity->id}/shortage_resolutions")
            ->assertUnauthorized();
    });
});
