<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\SendVersion;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\UnlockOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Guards\Opportunities\Contracts\ApprovalGate;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\PluginValidatorRegistry;
use App\Guards\Opportunities\Stages\AutoApprovalGate;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Store;
use App\Models\User;
use App\Services\NotificationRegistry;
use App\Services\Opportunities\Hooks\ApprovalChainRegistry;
use App\Services\Opportunities\Hooks\WorkflowTriggerRegistry;
use App\Services\Opportunities\TransitionRuleRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Build a Quotation opportunity carrying a single manual-priced line.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function guardedQuotation(?Store $store = null): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Guard pipeline',
        'store_id' => $store?->id,
        'starts_at' => '2026-10-01T09:00:00Z',
        'ends_at' => '2026-10-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return [$opportunity->refresh(), $opportunity->allItems()->firstOrFail()];
}

// ---------------------------------------------------------------------------
// Guard pipeline composition + placeholder seams
// ---------------------------------------------------------------------------

it('registers the four-stage pipeline with both core business rules', function () {
    $registry = app(TransitionRuleRegistry::class);

    expect($registry->has('shortage_confirmation'))->toBeTrue()
        ->and($registry->has('fx_tax_lock'))->toBeTrue()
        ->and(app(GuardPipeline::class))->toBeInstanceOf(GuardPipeline::class);
});

it('ships the approval + plugin-validator + consumer-hook seams as empty no-ops', function () {
    // Approval seam resolves to the no-op default.
    expect(app(ApprovalGate::class))->toBeInstanceOf(AutoApprovalGate::class);

    // Plugin validators + the workflow / approval-chain consumer hooks are empty.
    expect(app(PluginValidatorRegistry::class)->all())->toBe([])
        ->and(app(WorkflowTriggerRegistry::class)->all())->toBe([])
        ->and(app(ApprovalChainRegistry::class)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// FX/tax lock on convert-to-order + the unlock path
// ---------------------------------------------------------------------------

it('locks FX and tax when a quote is converted to an order', function () {
    [$opportunity] = guardedQuotation($this->store);

    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();

    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeTrue()
        ->and($opportunity->tax_locked)->toBeTrue();
});

it('rejects a rate edit on a locked order via the FX/tax-lock business rule', function () {
    [$opportunity, $item] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 9999]));
})->throws(ValidationException::class, 'exchange rate is locked');

it('releases both locks via UnlockOpportunity and re-allows the rate edit', function () {
    [$opportunity, $item] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new UnlockOpportunity)($opportunity->refresh(), 'Booked against the wrong day');
    $opportunity->refresh();

    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();

    // The previously-blocked rate edit now succeeds.
    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 9999]));

    expect($item->refresh()->unit_price)->toBe(9999);
});

it('rejects unlocking an opportunity that has no active locks', function () {
    [$opportunity] = guardedQuotation($this->store);

    (new UnlockOpportunity)($opportunity->refresh());
})->throws(EventNotValid::class);

it('forbids unlocking without the opportunities.unlock_rates permission', function () {
    [$opportunity] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    // A Sales user can edit opportunities but must NOT hold unlock_rates.
    $sales = User::factory()->create();
    $sales->assignRole('Sales');
    $this->actingAs($sales);

    expect($sales->can('opportunities.unlock_rates'))->toBeFalse();

    (new UnlockOpportunity)($opportunity->refresh());
})->throws(AuthorizationException::class);

it('rejects a discount edit on a locked order via the FX/tax-lock business rule', function () {
    [$opportunity, $item] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '10']));
})->throws(ValidationException::class, 'exchange rate is locked');

it('rejects setting a deal price on a locked order via the FX/tax-lock business rule', function () {
    [$opportunity] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 12345]));
})->throws(ValidationException::class, 'exchange rate is locked');

it('rejects clearing a deal price on a locked order via the FX/tax-lock business rule', function () {
    [$opportunity] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());

    (new ClearDealPrice)($opportunity->refresh());
})->throws(ValidationException::class, 'exchange rate is locked');

it('allows a discount and deal price once the locks are released', function () {
    [$opportunity, $item] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    (new UnlockOpportunity)($opportunity->refresh(), 'correcting the booking');

    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '10']));
    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 12345]));

    expect($item->refresh()->discount_percent)->toBe('10.00')
        ->and((int) $opportunity->refresh()->deal_total)->toBe(12345);
});

it('replays the locks-released event to the same cleared projection', function () {
    [$opportunity] = guardedQuotation($this->store);
    (new ConvertToOrder)($opportunity->refresh());
    (new UnlockOpportunity)($opportunity->refresh());

    Verbs::commit();
    Opportunity::query()->whereKey($opportunity->id)->update([
        'exchange_rate_locked' => true,
        'tax_locked' => true,
    ]);

    Verbs::replay();

    $replayed = Opportunity::query()->whereKey($opportunity->id)->firstOrFail();
    expect($replayed->exchange_rate_locked)->toBeFalse()
        ->and($replayed->tax_locked)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Version-payload enrichment
// ---------------------------------------------------------------------------

it('records sent_to and sent_via on a version when it is sent', function () {
    [$opportunity] = guardedQuotation($this->store);
    $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $recipient = Member::factory()->organisation()->create();

    (new SendVersion)(
        OpportunityVersion::query()->whereKey($version->id)->firstOrFail(),
        $recipient->id,
        'email',
    );

    $sent = OpportunityVersion::query()->whereKey($version->id)->firstOrFail();
    expect($sent->sent_to)->toBe($recipient->id)
        ->and($sent->sent_via)->toBe('email')
        ->and($sent->sent_at)->not->toBeNull();
});

it('records accepted_by on a version when it is accepted', function () {
    [$opportunity] = guardedQuotation($this->store);
    $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $acceptor = Member::factory()->contact()->create();

    (new AcceptVersion)(
        OpportunityVersion::query()->whereKey($version->id)->firstOrFail(),
        $acceptor->id,
    );

    expect(OpportunityVersion::query()->whereKey($version->id)->firstOrFail()->accepted_by)->toBe($acceptor->id);
});

it('registers the waitlist-match notification type as a placeholder hook', function () {
    $type = app(NotificationRegistry::class)->get('shortage.waitlist.matched');

    expect($type)->not->toBeNull()
        ->and($type['category'])->toBe('Opportunities')
        // Delivery deferred: no default channel until a recipient exists.
        ->and($type['default_channels'])->toBe([]);
});
