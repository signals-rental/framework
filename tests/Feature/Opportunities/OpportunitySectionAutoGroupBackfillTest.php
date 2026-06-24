<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunitySection;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\SequenceAllocator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Eager group-unification backfill
|--------------------------------------------------------------------------
|
| The backfill migration reproduces the historical render-time auto-bucketing as
| REAL persisted sections and assigns every null-section line to its bucket, so no
| line relies on the null-section render path afterwards. These tests drive the
| migration's up()/down() directly against fixtures.
|
*/

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    Auth::login($this->owner);

    // The unified cutover drops opportunity_sections; this suite exercises the
    // historical backfill migration against the pre-cutover schema only.
    cutoverMigration()->down();
});

function cutoverMigration(): object
{
    return require database_path('migrations/2026_06_22_230001_cutover_unified_opportunity_line_items.php');
}

function backfillMigration(): object
{
    return require database_path('migrations/2026_06_22_154417_backfill_opportunity_section_auto_groups.php');
}

function insertLegacyNullSectionLine(Opportunity $opportunity, string $name, ?Product $product, int $sortOrder = 0): void
{
    $id = app(SequenceAllocator::class)->next('opportunity_items');

    OpportunityItem::query()->insert([
        'id' => $id,
        'state_id' => snowflake_id(),
        'opportunity_id' => $opportunity->id,
        'item_id' => $product?->id,
        'item_type' => $product !== null ? Product::class : null,
        'name' => $name,
        'quantity' => 1,
        'unit_price' => 1000,
        'charge_period' => ChargePeriod::Day->value,
        'total' => 1000,
        'transaction_type' => LineItemTransactionType::Rental->value,
        'sort_order' => $sortOrder,
        'section_id' => null,
        'is_optional' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Build an opportunity carrying line items with section_id forced to NULL (the
 * pre-unification state the backfill heals).
 *
 * @param  array<int, array{name: string, product: Product|null}>  $lines
 */
function opportunityWithNullSectionLines(array $lines): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Backfill fixture']));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    foreach ($lines as $index => $line) {
        insertLegacyNullSectionLine($opportunity, $line['name'], $line['product'], $index);
    }

    return $opportunity;
}

it('creates one section per distinct auto-group bucket and assigns the lines', function () {
    $lighting = ProductGroup::factory()->create(['name' => 'Lighting']);
    $audio = ProductGroup::factory()->create(['name' => 'Audio']);

    $par = Product::factory()->create(['name' => 'PAR Can', 'product_group_id' => $lighting->id]);
    $movingHead = Product::factory()->create(['name' => 'Moving Head', 'product_group_id' => $lighting->id]);
    $speaker = Product::factory()->create(['name' => 'Speaker', 'product_group_id' => $audio->id]);
    $noGroup = Product::factory()->create(['name' => 'Mystery Box', 'product_group_id' => null]);

    $opportunity = opportunityWithNullSectionLines([
        ['name' => 'PAR Can', 'product' => $par],
        ['name' => 'Moving Head', 'product' => $movingHead],
        ['name' => 'Speaker', 'product' => $speaker],
        ['name' => 'Mystery Box', 'product' => $noGroup],
        ['name' => 'Service charge', 'product' => null], // non-product => auto:other
    ]);

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->whereNull('section_id')->count())->toBe(5);

    backfillMigration()->up();

    // One section per distinct bucket: Lighting, Audio, Ungrouped (no group), Other.
    $sections = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->whereNotNull('auto_group_key')
        ->get()
        ->keyBy('auto_group_key');

    expect($sections)->toHaveCount(4)
        ->and($sections->get('auto:'.$lighting->id)?->name)->toBe('Lighting')
        ->and($sections->get('auto:'.$audio->id)?->name)->toBe('Audio')
        ->and($sections->has('auto:ungrouped'))->toBeTrue()
        ->and($sections->has('auto:other'))->toBeTrue();

    // No line is left in the null-section render path.
    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->whereNull('section_id')->count())->toBe(0);

    // The two Lighting lines share ONE section.
    $lightingLines = OpportunityItem::query()
        ->where('opportunity_id', $opportunity->id)
        ->whereIn('name', ['PAR Can', 'Moving Head'])
        ->get();

    expect($lightingLines->pluck('section_id')->unique()->all())
        ->toBe([$sections->get('auto:'.$lighting->id)->id]);
});

it('appends auto-group sections AFTER existing user sections (sort_order)', function () {
    $group = ProductGroup::factory()->create(['name' => 'Rigging']);
    $product = Product::factory()->create(['name' => 'Truss', 'product_group_id' => $group->id]);

    $opportunity = opportunityWithNullSectionLines([
        ['name' => 'Truss', 'product' => $product],
    ]);

    // A pre-existing user section at sort_order 0.
    $userSection = OpportunitySection::factory()->for($opportunity)->create(['name' => 'Front of House', 'sort_order' => 0]);

    backfillMigration()->up();

    $auto = OpportunitySection::query()
        ->where('opportunity_id', $opportunity->id)
        ->where('auto_group_key', 'auto:'.$group->id)
        ->firstOrFail();

    expect($auto->sort_order)->toBeGreaterThan($userSection->sort_order);
});

it('is idempotent — re-running creates no duplicate sections and re-assigns the same lines', function () {
    $group = ProductGroup::factory()->create(['name' => 'Cabling']);
    $product = Product::factory()->create(['name' => 'XLR', 'product_group_id' => $group->id]);

    $opportunity = opportunityWithNullSectionLines([
        ['name' => 'XLR', 'product' => $product],
    ]);

    backfillMigration()->up();

    $sectionCount = OpportunitySection::query()->where('opportunity_id', $opportunity->id)->count();
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    $sectionId = (int) DB::table('opportunity_items')->where('id', $item->id)->value('section_id');

    // Re-run.
    backfillMigration()->up();

    expect(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->count())->toBe($sectionCount)
        ->and((int) DB::table('opportunity_items')->where('id', $item->id)->value('section_id'))->toBe($sectionId)
        ->and(DB::table('opportunity_items')->where('opportunity_id', $opportunity->id)->whereNull('section_id')->count())->toBe(0);
});

it('does not disturb lines already assigned to a user section', function () {
    $group = ProductGroup::factory()->create(['name' => 'Staging']);
    $product = Product::factory()->create(['name' => 'Deck', 'product_group_id' => $group->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Mixed']));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    $userSection = OpportunitySection::factory()->for($opportunity)->create(['name' => 'Keep me']);

    insertLegacyNullSectionLine($opportunity, 'Deck', $product);
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->firstOrFail();
    DB::table('opportunity_items')
        ->where('id', $item->id)
        ->update(['section_id' => $userSection->id]);

    backfillMigration()->up();

    // The already-sectioned line is untouched; no auto-group section is created.
    expect((int) DB::table('opportunity_items')->where('id', $item->id)->value('section_id'))
        ->toBe($userSection->id)
        ->and(OpportunitySection::query()->where('opportunity_id', $opportunity->id)->whereNotNull('auto_group_key')->count())->toBe(0);
});

it('reverses the backfill (down) by deleting auto sections and nulling their lines', function () {
    $group = ProductGroup::factory()->create(['name' => 'Power']);
    $product = Product::factory()->create(['name' => 'Distro', 'product_group_id' => $group->id]);

    $opportunity = opportunityWithNullSectionLines([
        ['name' => 'Distro', 'product' => $product],
    ]);
    $userSection = OpportunitySection::factory()->for($opportunity)->create(['name' => 'User']);

    backfillMigration()->up();

    expect(OpportunitySection::query()->whereNotNull('auto_group_key')->count())->toBeGreaterThan(0);

    backfillMigration()->down();

    // Auto sections are gone, their lines null again, the user section survives.
    expect(OpportunitySection::query()->whereNotNull('auto_group_key')->count())->toBe(0)
        ->and(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->whereNull('section_id')->count())->toBe(1)
        ->and(OpportunitySection::query()->whereKey($userSection->id)->exists())->toBeTrue();
});
