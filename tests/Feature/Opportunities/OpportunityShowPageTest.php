<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\DeleteOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\CustomFieldType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
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

it('gates every allowed Actions split-button item behind the shared confirm modal', function () {
    // B1: each allowed Actions item stages a styled confirmation rather than firing a
    // native wire:confirm dialog — it calls prepareAction(...) and opens the shared
    // `confirm-action` modal. A draft has convert_to_quotation, clone and archive
    // allowed for an owner, each rendered as a prepareAction call (NOT wire:confirm).
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        // The native JS confirm has been removed entirely.
        ->assertDontSeeHtml('wire:confirm')
        // Each allowed item stages its key through prepareAction and opens the modal.
        ->assertSeeHtml("prepareAction('convert_to_quotation'")
        ->assertSeeHtml("prepareAction('clone'")
        ->assertSeeHtml("prepareAction('delete'")
        ->assertSeeHtml("open-modal', 'confirm-action'")
        // The single shared confirm modal + its confirm trigger are present.
        ->assertSeeHtml('wire:click="confirmPendingAction"');
});

it('runs the staged Actions item through confirmPendingAction (clone)', function () {
    // Staging `clone` then confirming must dispatch to cloneOpportunity, which
    // clones the opportunity and redirects to the new record's show page.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Clone Via Modal');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('prepareAction', 'clone', 'Clone', 'Clone this opportunity into a new draft?')
        ->assertSet('pendingAction', 'clone')
        ->call('confirmPendingAction')
        ->assertSet('pendingAction', null)
        ->assertRedirect();

    // A second opportunity now exists (the clone).
    expect(Opportunity::query()->count())->toBe(2);
});

it('runs the staged Actions item through confirmPendingAction (convert_to_quotation)', function () {
    // Staging `convert_to_quotation` then confirming must dispatch to the
    // convertToQuotation transition, moving the draft to a quotation.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Convert Via Modal');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('prepareAction', 'convert_to_quotation', 'Convert to Quotation', 'Convert this opportunity to a quotation?')
        ->call('confirmPendingAction')
        ->assertSet('pendingAction', null);

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('runs the staged Actions item through confirmPendingAction (revert_to_draft)', function () {
    // Staging `revert_to_draft` then confirming must dispatch to the revertToDraft
    // transition, moving the open quotation back to a draft.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Revert To Draft Via Modal');
    (new ConvertToQuotation)($opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('prepareAction', 'revert_to_draft', 'Revert to Draft', 'Revert this quotation back to a draft?')
        ->call('confirmPendingAction')
        ->assertSet('pendingAction', null);

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Draft)
        ->and($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::DraftOpen);
});

it('runs the staged Actions item through confirmPendingAction (reopen)', function () {
    // Staging `reopen` then confirming must dispatch to the reopen transition,
    // moving the completed order back to an active order.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Reopen Via Modal');
    (new ConvertToQuotation)($opportunity);
    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '1',
        'unit_price' => 5000,
    ]));
    (new ConvertToOrder)($opportunity->fresh());
    (new ChangeOpportunityStatus)($opportunity->fresh(), OpportunityStatus::OrderComplete);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderComplete);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('prepareAction', 'reopen', 'Re-open', 'Re-open this completed order back to an active order?')
        ->call('confirmPendingAction')
        ->assertSet('pendingAction', null);

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);
});

it('ignores a confirmPendingAction call with no staged action', function () {
    // Defensive: confirming with nothing staged is a harmless no-op (the draft is
    // unchanged) and clears any pending state.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'No Staged Action');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('confirmPendingAction')
        ->assertSet('pendingAction', null);

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Draft);
});

it('does not stage an unconfirmable key via prepareAction', function () {
    // prepareAction whitelists the confirmable keys; an unknown key is not staged.
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('prepareAction', 'not_a_real_action', 'Bogus', 'Nope')
        ->assertSet('pendingAction', null);
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

it('embeds the line-item editor in the Overview main area', function () {
    // The show-page restructure merged the standalone Line Items tab into the
    // Overview main column: the Show page now nests <livewire:opportunities.items>
    // as a child component. Asserting the nested component is present (its
    // wire:id marker) proves the editor is embedded, not a separate page.
    $opportunity = Opportunity::factory()->create(['subject' => 'Embedded Editor']);

    $this->actingAs($this->owner);

    // The nested editor renders with wire:name="opportunities.items"; asserting that
    // marker in the Show page's HTML proves the editor is embedded, not a separate page.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSeeHtml('wire:name="opportunities.items"');
});

it('shows the compact totals block in the Overview sidebar', function () {
    $opportunity = Opportunity::factory()->create()->fresh();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('Charge Total')
        ->assertSee('Excl. Tax');
});

it('shows the active version number under Number in Key Attributes', function () {
    // The Key Attributes panel surfaces a "Version" row (v{n} + its date) directly
    // under "Number", derived from the opportunity's active version. With no active
    // version the row is omitted gracefully.
    $opportunity = Opportunity::factory()->quotation()->create();

    $version = OpportunityVersion::factory()->create([
        'opportunity_id' => $opportunity->id,
        'version_number' => 3,
    ]);

    $opportunity->forceFill(['active_version_id' => $version->id])->save();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertSee('Version')
        ->assertSee('v3');
});

it('omits the version row when the opportunity has no active version', function () {
    $opportunity = Opportunity::factory()->create(['active_version_id' => 0]);

    $this->actingAs($this->owner);

    // 'v' + a number never appears when there is no active version (active_version_id 0).
    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertDontSee('v1');
});

it('shows the active version number in the page header after the subject', function () {
    // B6: the page-header title carries the active version number after the subject
    // (e.g. "Subject — v3"), derived from the opportunity's active version.
    $opportunity = Opportunity::factory()->quotation()->create(['subject' => 'Header Version Opp']);

    $version = OpportunityVersion::factory()->create([
        'opportunity_id' => $opportunity->id,
        'version_number' => 4,
    ]);

    $opportunity->forceFill(['active_version_id' => $version->id])->save();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertSee('Header Version Opp — v4');
});

it('omits the header version suffix when the opportunity has no active version', function () {
    // B6: with no active version the title is just the subject (no "— v" suffix).
    $opportunity = Opportunity::factory()->create(['subject' => 'No Header Version', 'active_version_id' => 0]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertSee('No Header Version')
        ->assertDontSee('No Header Version — v');
});

it('hides the Assets and Shortages tabs for a draft opportunity', function () {
    // B3: allocation/dispatch + shortage resolution only apply to a reserved
    // quotation or an order — a draft shows neither tab.
    $opportunity = Opportunity::factory()->create(['subject' => 'Draft Tabs']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertDontSee(route('opportunities.assets', $opportunity))
        ->assertDontSee(route('opportunities.shortages', $opportunity));
});

it('hides the Assets and Shortages tabs for an open (non-reserved) quotation', function () {
    // B3: a provisional quotation is not yet reserved, so the allocation tabs stay
    // hidden.
    $opportunity = Opportunity::factory()->quotation()->create(['subject' => 'Open Quote Tabs']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertDontSee(route('opportunities.assets', $opportunity))
        ->assertDontSee(route('opportunities.shortages', $opportunity));
});

it('shows the Assets and Shortages tabs for a reserved quotation', function () {
    // B3: a quotation in Reserved status DOES surface both allocation tabs.
    $opportunity = Opportunity::factory()->reserved()->create(['subject' => 'Reserved Quote Tabs']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee(route('opportunities.assets', $opportunity))
        ->assertSee(route('opportunities.shortages', $opportunity));
});

it('shows the Assets and Shortages tabs for an order', function () {
    // B3: an order surfaces both allocation tabs.
    $opportunity = Opportunity::factory()->order()->create(['subject' => 'Order Tabs']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee(route('opportunities.assets', $opportunity))
        ->assertSee(route('opportunities.shortages', $opportunity));
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

it('renders the embedded line-items editor component', function () {
    // The line-item editor is now embedded in the Overview (no standalone route);
    // Volt::test still renders the component directly without a route. A factory row
    // (no live Verbs stream) renders read-only; editor mutations are covered in
    // OpportunityItemsEditorTest against a live opportunity.
    $opportunity = Opportunity::factory()->create(['subject' => 'Items Demo']);

    $this->actingAs($this->owner);

    Volt::test('opportunities.items', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('No line items');
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

it('restores an archived opportunity via the restore action on the Show page', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Restore On Show');
    (new DeleteOpportunity)($opportunity);
    $opportunity = $opportunity->fresh();

    expect($opportunity->trashed())->toBeTrue();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('restore');

    expect($opportunity->fresh()->trashed())->toBeFalse();
});

it('offers only the legal target statuses for the current state in the change_status picker', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Status Demo');
    (new ConvertToQuotation)($opportunity);
    $opportunity = $opportunity->fresh();

    $this->actingAs($this->owner);

    // The picker (statusOptions, surfaced via with()) lists every Quotation status
    // except the current Provisional one — and never an Order/Draft status.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertViewHas('statusOptions', function (array $options): bool {
            $labels = collect($options)->pluck('label')->all();

            return in_array('Reserved', $labels, true)
                && in_array('Lost', $labels, true)
                && ! in_array('Provisional', $labels, true) // the current status is excluded
                && ! in_array('Active', $labels, true);      // an Order status is never offered
        });
});

it('moves a quotation to a legal status via the change_status picker', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Status Move');
    (new ConvertToQuotation)($opportunity);
    $opportunity = $opportunity->fresh();

    $this->actingAs($this->owner);

    // Provisional (0) → Reserved (1) within the Quotation state.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('changeStatus', OpportunityStatus::QuotationReserved->statusValue());

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationReserved);
});

it('closes the change-status modal on a successful status change', function () {
    // B2: a successful change_status move dispatches `close-modal` for `change-status`
    // so the picker modal closes.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Close On Success');
    (new ConvertToQuotation)($opportunity);
    $opportunity = $opportunity->fresh();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('changeStatus', OpportunityStatus::QuotationReserved->statusValue())
        ->assertDispatched('close-modal', 'change-status');

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationReserved);
});

it('keeps the change-status modal open when the target status is invalid', function () {
    // B2: an illegal target flashes an error and leaves the status unchanged — the
    // modal stays open (no close-modal dispatch) so the reason is visible.
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Stay Open On Error');
    (new ConvertToQuotation)($opportunity);
    $opportunity = $opportunity->fresh();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('changeStatus', 9)
        ->assertNotDispatched('close-modal');

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);
});

it('rejects an illegal change_status target not belonging to the current state', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Illegal Status');
    (new ConvertToQuotation)($opportunity);
    $opportunity = $opportunity->fresh();

    $this->actingAs($this->owner);

    // A per-state status value of 9 maps to no OpportunityStatus case for the
    // Quotation state (1*100+9), so the picker's enum-derived guard refuses it.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('changeStatus', 9)
        ->assertSee('not valid for the opportunity');

    // Status unchanged — still Provisional.
    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::QuotationProvisional);
});
