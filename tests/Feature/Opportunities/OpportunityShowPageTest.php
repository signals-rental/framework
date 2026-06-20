<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\CustomFieldType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\ActionLog;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Opportunity Show page (M8-2)
|--------------------------------------------------------------------------
|
| The Show overview renders the record, computes the permitted state-transition
| actions the same way the API's available_actions endpoint does (permission
| probe → state precondition → non-throwing GuardPipeline dry-run), and exposes
| the transition wire-methods. Factory rows are fine for render/gating/timeline/
| custom-field assertions; the transition-execution test creates a real
| opportunity through the CreateOpportunity action so it carries a live Verbs
| state (factory rows have a synthetic state_id with no event stream).
|
*/

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * Create an opportunity through the real event pipeline so it has a live Verbs
 * state that lifecycle transitions can resolve.
 */
function createLiveOpportunity(User $actor, int $storeId, string $subject = 'Live opportunity'): Opportunity
{
    Auth::login($actor);

    $result = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => $subject,
        'store_id' => $storeId,
    ]));

    return Opportunity::query()->whereKey($result->id)->firstOrFail();
}

it('renders the show page for an opportunity', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'Demo Opportunity']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Demo Opportunity');
});

it('forbids the show page for a user without opportunities.view', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])->assertForbidden();
});

it('surfaces convert_to_quotation as allowed and convert_to_order as not allowed for a draft', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    $actions = collect(Volt::test('opportunities.show', ['opportunity' => $opportunity])->viewData('availableActions'))
        ->keyBy('key');

    expect($actions['convert_to_quotation']['allowed'])->toBeTrue()
        ->and($actions['convert_to_order']['allowed'])->toBeFalse()
        ->and($actions['convert_to_order']['code'])->toBe('invalid_state');
});

it('surfaces convert_to_order as allowed for an open quotation', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    $actions = collect(Volt::test('opportunities.show', ['opportunity' => $opportunity])->viewData('availableActions'))
        ->keyBy('key');

    expect($actions['convert_to_order']['allowed'])->toBeTrue()
        ->and($actions['convert_to_quotation']['allowed'])->toBeFalse();
});

it('renders the convert-to-quotation action button on a draft for an owner', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertSee('Convert to Quotation');
});

it('gates edit-requiring actions as permission_denied for a view-only user', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('opportunities.access', 'opportunities.view');
    $this->actingAs($viewer);

    $actions = collect(Volt::test('opportunities.show', ['opportunity' => $opportunity])->viewData('availableActions'))
        ->keyBy('key');

    // Convert (needs opportunities.edit), clone (needs opportunities.create) and
    // archive (needs opportunities.delete) are all denied for permission reasons.
    expect($actions['convert_to_quotation']['allowed'])->toBeFalse()
        ->and($actions['convert_to_quotation']['code'])->toBe('permission_denied')
        ->and($actions['clone']['allowed'])->toBeFalse()
        ->and($actions['clone']['code'])->toBe('permission_denied')
        ->and($actions['delete']['allowed'])->toBeFalse()
        ->and($actions['delete']['code'])->toBe('permission_denied');
});

it('renders the live audit timeline for the opportunity', function () {
    $opportunity = Opportunity::factory()->create();

    ActionLog::factory()->forUser($this->owner)->create([
        'action' => 'opportunity.created',
        'auditable_type' => $opportunity->getMorphClass(),
        'auditable_id' => $opportunity->id,
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Opportunity Created');
});

it('renders a configured custom field on the custom-fields tab', function () {
    $opportunity = Opportunity::factory()->create();

    $field = CustomField::factory()->create([
        'module_type' => 'Opportunity',
        'name' => 'po_reference',
        'display_name' => 'PO Reference',
        'field_type' => CustomFieldType::String,
    ]);

    CustomFieldValue::query()->create([
        'custom_field_id' => $field->id,
        'entity_type' => $opportunity->getMorphClass(),
        'entity_id' => $opportunity->id,
        'value_string' => 'PO-12345',
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.custom-fields', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('PO Reference');
});

it('forbids the custom-fields tab for a user without opportunities.view', function () {
    $opportunity = Opportunity::factory()->create();

    $viewer = User::factory()->create();
    $this->actingAs($viewer);

    Volt::test('opportunities.custom-fields', ['opportunity' => $opportunity])->assertForbidden();
});

it('renders the line-items editor tab', function () {
    // The items tab is the M8-3d editor now; a factory row (no live Verbs stream)
    // still renders read-only. Editor mutations are covered in
    // OpportunityItemsEditorTest against a live opportunity.
    $opportunity = Opportunity::factory()->create(['subject' => 'Items Demo']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Line Items');
});

it('transitions a draft to a quotation via the convertToQuotation action', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Transition Demo');

    expect($opportunity->state)->toBe(OpportunityState::Draft);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('convertToQuotation');

    $fresh = $opportunity->fresh();

    expect($fresh->state)->toBe(OpportunityState::Quotation)
        ->and($fresh->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);
});
