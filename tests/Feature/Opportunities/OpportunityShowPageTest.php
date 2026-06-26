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

it('shows tag_list chips in the page header next to state and status badges', function () {
    $opportunity = Opportunity::factory()->create([
        'subject' => 'Tagged Opportunity',
        'tag_list' => ['vip', 'rush'],
    ]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSee('vip')
        ->assertSee('rush')
        ->assertSeeHtml('class="s-chip"');
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

it('labels the rates action Lock price when unlocked and Unlock price when locked', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    $unlocked = collect(Volt::test('opportunities.show', ['opportunity' => $opportunity])->viewData('availableActions'))
        ->keyBy('key');

    expect($unlocked['unlock_locks']['label'])->toBe('Lock price')
        ->and($unlocked['unlock_locks']['allowed'])->toBeTrue();

    $opportunity->update(['exchange_rate_locked' => true]);

    $locked = collect(Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])->viewData('availableActions'))
        ->keyBy('key');

    expect($locked['unlock_locks']['label'])->toBe('Unlock price')
        ->and($locked['unlock_locks']['allowed'])->toBeTrue();
});

it('locks rates via unlockRates when no locks are active', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('unlockRates');

    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();
});

it('renders the convert-to-quotation action button on a draft for an owner', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertSee('Convert to Quotation');
});

it('opens confirm modals for transition actions except convert flows', function () {
    $opportunity = Opportunity::factory()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSeeHtml("openConvertModal('convert_to_quotation'")
        ->assertSeeHtml('convert-opportunity')
        ->assertSeeHtml('wire:click="confirmConvert"')
        ->assertSeeHtml("openConfirmModal('clone'")
        ->assertSeeHtml("openConfirmModal('unlock_locks'")
        ->assertSeeHtml('confirm-opportunity-action')
        ->assertSeeHtml('wire:click="confirmTransition"')
        ->assertDontSeeHtml('wire:click="cloneOpportunity"')
        ->assertDontSeeHtml('wire:click="unlockRates"')
        ->assertDontSeeHtml('wire:click="convertToQuotation"');
});

it('confirms clone via the shared confirm modal', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Clone Modal');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('openConfirmModal', 'clone')
        ->assertSet('pendingConfirmKey', 'clone')
        ->call('confirmTransition')
        ->assertSet('pendingConfirmKey', null)
        ->assertRedirect();

    expect(Opportunity::query()->count())->toBe(2);
});

it('confirms lock rates via the shared confirm modal', function () {
    $opportunity = Opportunity::factory()->quotation()->create();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('openConfirmModal', 'unlock_locks')
        ->assertSet('pendingConfirmKey', 'unlock_locks')
        ->call('confirmTransition')
        ->assertSet('pendingConfirmKey', null)
        ->assertDispatched('toast', type: 'success', message: 'Price locked');

    expect($opportunity->fresh()->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->fresh()->tax_locked)->toBeTrue();
});

it('converts via the convert modal and shows a success toast', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Convert Modal');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('openConvertModal', 'convert_to_quotation')
        ->assertSet('pendingConvertKey', 'convert_to_quotation')
        ->call('confirmConvert')
        ->assertSet('pendingConvertKey', null)
        ->assertDispatched('toast', type: 'success', message: 'Converted to quotation')
        ->assertDispatched('close-modal', 'convert-opportunity')
        ->assertDispatched('opportunity-lifecycle-changed');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('transitions the opportunity when convertToQuotation is called directly', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Convert Direct');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('convertToQuotation')
        ->assertDispatched('opportunity-lifecycle-changed');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation);
});

it('clones the opportunity when cloneOpportunity is called directly', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Clone Direct');

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('cloneOpportunity')
        ->assertRedirect();

    expect(Opportunity::query()->count())->toBe(2);
});

it('reverts to draft when revertToDraft is called directly', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Revert To Draft Direct');
    (new ConvertToQuotation)($opportunity);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->call('revertToDraft');

    expect($opportunity->fresh()->state)->toBe(OpportunityState::Draft)
        ->and($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::DraftOpen);
});

it('reopens a completed order when reopen is called directly', function () {
    $opportunity = createLiveOpportunity($this->owner, $this->store->id, 'Reopen Direct');
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
        ->call('reopen');

    expect($opportunity->fresh()->statusEnum())->toBe(OpportunityStatus::OrderActive);
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
    // Overview main column: the Show page now nests <livewire:opportunities.line-items>
    // as a child component. Asserting the nested component is present (its
    // wire:id marker) proves the editor is embedded, not a separate page.
    $opportunity = Opportunity::factory()->create(['subject' => 'Embedded Editor']);

    $this->actingAs($this->owner);

    // The nested editor renders with wire:name="opportunities.line-items"; asserting that
    // marker in the Show page's HTML proves the editor is embedded, not a separate page.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->assertOk()
        ->assertSeeHtml('wire:name="opportunities.line-items"');
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

it('omits the Key Attributes version row when the opportunity has no active version', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'No Version Row Opp', 'active_version_id' => 0]);

    $this->actingAs($this->owner);

    // The Key Attributes "Version" data-row is omitted when there is no active
    // version (active_version_id 0). The header title still shows a default "— v1"
    // (#4), so we assert the data-list row label specifically is absent.
    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertDontSeeHtml('s-data-list-label">Version</div>');
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

it('defaults the header version suffix to v1 when the opportunity has no active version (#4)', function () {
    // #4: the page-header title ALWAYS shows a version number — when there is no
    // explicit versioning it defaults to v1 (derived from version_count, min 1).
    $opportunity = Opportunity::factory()->create(['subject' => 'No Header Version', 'active_version_id' => 0]);

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity->fresh()])
        ->assertOk()
        ->assertSee('No Header Version — v1');
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

    Volt::test('opportunities.line-items', ['opportunity' => $opportunity])
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

it('refreshes the projection when the line-item editor reports a totals change (#10)', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'Totals Listener Opp'])->fresh();

    $this->actingAs($this->owner);

    // The Show component listens for opportunity-totals-updated (dispatched by the
    // nested line-item editor after a qty/rate/discount change) and refreshes its
    // own projection so the sidebar Totals + header stay in sync.
    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('onTotalsUpdated')
        ->assertOk();
});

it('applies an optimistic charge total from the editor without waiting for refresh (#371)', function () {
    $opportunity = Opportunity::factory()->create([
        'subject' => 'Optimistic Totals Opp',
        'charge_total' => 12500,
    ])->fresh();

    $this->actingAs($this->owner);

    Volt::test('opportunities.show', ['opportunity' => $opportunity])
        ->call('onTotalsUpdated', 7500)
        ->assertSet('opportunity.charge_total', 7500);
});
