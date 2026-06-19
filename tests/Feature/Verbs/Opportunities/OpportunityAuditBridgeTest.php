<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Events\AuditableEvent;
use App\Models\ActionLog;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Schema;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('records an audit row when an opportunity is created', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Audited create',
        'reference' => 'PO-1',
    ]));

    $logs = ActionLog::query()
        ->where('action', 'opportunity.created')
        ->where('auditable_id', $created->id)
        ->get();

    expect($logs)->toHaveCount(1);

    $log = $logs->first();
    /** @var array<string, mixed> $newValues */
    $newValues = $log->new_values;

    expect($log->auditable_type)->toBe(Opportunity::class)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->verb_event_id)->not->toBeNull()
        ->and($log->old_values)->toBeNull();

    expect($newValues['subject'])->toBe('Audited create')
        ->and($newValues['state'])->toBe(OpportunityState::Draft->value)
        ->and($newValues['status'])->toBe(OpportunityStatus::DraftOpen->statusValue())
        ->and($newValues['reference'])->toBe('PO-1');
});

it('records an audit row when an opportunity is quoted', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Quote me']));

    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $log = ActionLog::query()
        ->where('action', 'opportunity.quoted')
        ->where('auditable_id', $created->id)
        ->sole();

    expect(ActionLog::query()->where('action', 'opportunity.quoted')->where('auditable_id', $created->id)->count())->toBe(1)
        ->and($log->auditable_type)->toBe(Opportunity::class)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->verb_event_id)->not->toBeNull()
        ->and($log->old_values)->toBe(['state' => OpportunityState::Draft->value, 'status' => OpportunityStatus::DraftOpen->statusValue()])
        ->and($log->new_values)->toBe(['state' => OpportunityState::Quotation->value, 'status' => OpportunityStatus::QuotationProvisional->statusValue()]);
});

it('records an audit row when a quotation is converted to an order', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'To order']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    // An order must carry at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard).
    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $log = ActionLog::query()
        ->where('action', 'opportunity.converted_to_order')
        ->where('auditable_id', $created->id)
        ->sole();

    expect(ActionLog::query()->where('action', 'opportunity.converted_to_order')->where('auditable_id', $created->id)->count())->toBe(1)
        ->and($log->auditable_type)->toBe(Opportunity::class)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->verb_event_id)->not->toBeNull()
        ->and($log->old_values)->toBe(['state' => OpportunityState::Quotation->value, 'status' => OpportunityStatus::QuotationProvisional->statusValue()])
        // Converting to an order also locks the FX rate + tax (MC §4.3/§7.2), which
        // the audit snapshot now records alongside the state/status transition.
        ->and($log->new_values)->toBe([
            'state' => OpportunityState::Order->value,
            'status' => OpportunityStatus::OrderActive->statusValue(),
            'exchange_rate_locked' => true,
            'tax_locked' => true,
        ]);
});

it('records an audit row when a status changes within a state', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Reserve me']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new ChangeOpportunityStatus)(
        Opportunity::findOrFail($created->id),
        OpportunityStatus::QuotationReserved,
    );

    $log = ActionLog::query()
        ->where('action', 'opportunity.status_changed')
        ->where('auditable_id', $created->id)
        ->sole();

    expect(ActionLog::query()->where('action', 'opportunity.status_changed')->where('auditable_id', $created->id)->count())->toBe(1)
        ->and($log->auditable_type)->toBe(Opportunity::class)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->verb_event_id)->not->toBeNull()
        ->and($log->old_values)->toBe(['status' => OpportunityStatus::QuotationProvisional->statusValue()])
        ->and($log->new_values)->toBe(['status' => OpportunityStatus::QuotationReserved->statusValue()]);
});

it('records an audit row when editable header fields are updated', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Original']));

    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'subject' => 'Updated subject',
            'reference' => 'NEW-REF',
        ]),
    );

    $log = ActionLog::query()
        ->where('action', 'opportunity.updated')
        ->where('auditable_id', $created->id)
        ->sole();

    expect(ActionLog::query()->where('action', 'opportunity.updated')->where('auditable_id', $created->id)->count())->toBe(1)
        ->and($log->auditable_type)->toBe(Opportunity::class)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->verb_event_id)->not->toBeNull()
        // Only changed fields are captured (partial update mirrors apply()).
        ->and($log->new_values)->toBe(['subject' => 'Updated subject', 'reference' => 'NEW-REF'])
        ->and($log->old_values)->toBe(['subject' => 'Original', 'reference' => null]);
});

it('does not duplicate audit rows on replay', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Replay safe']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
    // An order must carry at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard); this also writes an
    // opportunity.item_added audit row.
    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));
    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $id = $created->id;
    $countBefore = ActionLog::query()->where('auditable_id', $id)->count();

    // created + quoted + item_added + converted_to_order.
    expect($countBefore)->toBe(4);

    // Replay re-runs every handle() (Phase::Replay), which re-dispatches the
    // audit bridge — but firstOrCreate on verb_event_id makes each a no-op.
    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $id)->count())->toBe($countBefore);
});

it('preserves the original actor across replay even when audit rows are rebuilt', function () {
    $userA = $this->actor;

    // Fire the lifecycle as user A.
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Actor A']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $id = $created->id;

    expect(ActionLog::query()->where('auditable_id', $id)->pluck('user_id')->unique()->all())
        ->toBe([$userA->id]);

    // Delete the audit rows so replay must RE-CREATE them, then switch the live
    // auth context to a DIFFERENT user. If actor came from live auth() the
    // rebuilt rows would carry user B; instead they must carry user A, sourced
    // from the persisted (rehydrated) Verbs metadata.
    ActionLog::query()->delete();
    expect(ActionLog::query()->count())->toBe(0);

    $userB = User::factory()->owner()->create();
    $this->actingAs($userB);

    Verbs::replay();

    $rebuilt = ActionLog::query()->where('auditable_id', $id)->get();

    expect($rebuilt)->toHaveCount(2)
        ->and($rebuilt->pluck('user_id')->unique()->all())->toBe([$userA->id])
        ->and($rebuilt->pluck('user_id')->all())->not->toContain($userB->id);
});

it('rolls back the whole event-sourced commit when the audit insert fails', function () {
    // Force the audit insert inside the ES commit to fail by dropping the table
    // it writes to. The audit row is written from handle(), which runs inside the
    // commitVerbs() DB::transaction; the failure must propagate (NOT be swallowed)
    // so the whole commit rolls back — leaving no verb_events row and no
    // projected opportunity.
    Schema::drop('action_logs');

    $threw = false;

    try {
        (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audit doomed']));
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue()
        ->and(VerbEvent::query()->count())->toBe(0)
        ->and(Opportunity::query()->withTrashed()->count())->toBe(0);
});

it('keeps legacy non event-sourced audit byte-identical (no verb event id, live auth)', function () {
    $user = $this->actor;

    event(new AuditableEvent(
        model: $user,
        action: 'user.profile_updated',
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
    ));

    $log = ActionLog::query()->where('action', 'user.profile_updated')->sole();

    expect($log->verb_event_id)->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->auditable_type)->toBe(User::class)
        ->and($log->auditable_id)->toBe($user->id)
        ->and($log->old_values)->toBe(['name' => 'Old'])
        ->and($log->new_values)->toBe(['name' => 'New']);
});
