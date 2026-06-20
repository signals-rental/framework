<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AssignItemToSection;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AssignItemToSectionData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Facades\Verbs;

/*
|--------------------------------------------------------------------------
| PostgreSQL opportunity-sections lane
|--------------------------------------------------------------------------
|
| Proves the opportunity_items.section_id -> opportunity_sections FK behaves as
| a real nullOnDelete constraint on Postgres (the SQLite lane rebuilds the table,
| so this confirms the genuine DB-level FK action), and re-confirms the
| replay-safety invariant on real Postgres. Skips when Postgres is unreachable.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

function pgSectionOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'PG Sectioned']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

function pgSectionedItem(Opportunity $opportunity): OpportunityItem
{
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Speaker', 'quantity' => '2', 'unit_price' => 5000,
    ]));

    return $opportunity->refresh()->items()->firstOrFail();
}

it('nulls the line section_id at the database level when the section is deleted (real FK nullOnDelete)', function () {
    $opportunity = pgSectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = pgSectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    // Delete the section row directly (no action) so the constraint, not the
    // application, is what nulls the link.
    OpportunitySection::query()->whereKey($section->id)->delete();

    expect($item->refresh()->section_id)->toBeNull()
        ->and(OpportunityItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

it('preserves section_id across a Verbs replay on Postgres', function () {
    $opportunity = pgSectionOpportunity();
    $section = OpportunitySection::factory()->for($opportunity)->create();
    $item = pgSectionedItem($opportunity);

    (new AssignItemToSection)($item, AssignItemToSectionData::from(['section_id' => $section->id]));
    expect($item->refresh()->section_id)->toBe($section->id);

    Verbs::replay();

    expect($item->refresh()->section_id)->toBe($section->id);
});
