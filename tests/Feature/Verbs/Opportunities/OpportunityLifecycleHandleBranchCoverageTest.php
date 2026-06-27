<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use App\Verbs\Events\Opportunities\OpportunityDeleted;
use App\Verbs\Events\Opportunities\OpportunityLocksApplied;
use App\Verbs\Events\Opportunities\OpportunityLocksReleased;
use App\Verbs\Events\Opportunities\OpportunityQuoted;
use App\Verbs\Events\Opportunities\OpportunityReinstated;
use App\Verbs\Events\Opportunities\OpportunityReopened;
use App\Verbs\Events\Opportunities\OpportunityRevertedToDraft;
use App\Verbs\Events\Opportunities\OpportunityRevertedToQuotation;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use App\Verbs\Events\Opportunities\OpportunityStatusPromoted;
use App\Verbs\Events\Opportunities\OpportunityUpdated;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Thunk\Verbs\Facades\Verbs;

/**
 * Covers the `$oldRow === null` branch in opportunity-scoped lifecycle events'
 * handle() methods — the snapshot path taken when the `opportunities` projection
 * row was hard-deleted out from under a later event in the same Verbs stream.
 *
 * These events do not no-op like DealPriceSet (they call firstOrFail() after the
 * update), so the assertion documents the current failure mode: ModelNotFoundException
 * after the null old-row snapshot branch runs.
 */
beforeEach(function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

function lifecycleDraftOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Lifecycle handle branch',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

function lifecycleQuotationOpportunity(): Opportunity
{
    $opportunity = lifecycleDraftOpportunity();
    (new ConvertToQuotation)($opportunity);

    return $opportunity->refresh();
}

function lifecycleQuotationWithItemOpportunity(): Opportunity
{
    $opportunity = lifecycleQuotationOpportunity();
    $product = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    return $opportunity->refresh();
}

function lifecycleOrderOpportunity(): Opportunity
{
    $opportunity = lifecycleQuotationWithItemOpportunity();
    (new ConvertToOrder)($opportunity);

    return $opportunity->refresh();
}

/**
 * @param  callable(int): mixed  $fire  receives the opportunity Verbs state_id
 */
function fireOnMissingOpportunityProjection(Opportunity $opportunity, callable $fire): void
{
    $stateId = $opportunity->state_id;
    $opportunityId = $opportunity->id;

    Opportunity::query()->whereKey($opportunityId)->forceDelete();

    expect(fn () => DB::transaction(function () use ($stateId, $fire): void {
        $fire($stateId);
        Verbs::commit();
    }))->toThrow(ModelNotFoundException::class);

    expect(Opportunity::withTrashed()->whereKey($opportunityId)->exists())->toBeFalse();
}

it('lifecycle handle() reads a null old-row snapshot before the projection lookup fails', function (callable $setup, callable $fire) {
    $setup = $setup();
    $fire = $fire();

    $opportunity = $setup();

    fireOnMissingOpportunityProjection($opportunity, $fire);
})->with([
    'quoted' => [
        fn () => fn (): Opportunity => lifecycleDraftOpportunity(),
        fn () => fn (int $id) => OpportunityQuoted::fire(opportunity_id: $id),
    ],
    'reverted to draft' => [
        fn () => fn (): Opportunity => lifecycleQuotationOpportunity(),
        fn () => fn (int $id) => OpportunityRevertedToDraft::fire(opportunity_id: $id, reason: 'too early'),
    ],
    // OpportunityConvertedToOrder validate() reads the projection via
    // opportunityHasActiveItem(), so a missing row fails validate before handle()
    // — the null old-row snapshot branch is genuinely unreachable (see report).
    'reverted to quotation' => [
        fn () => fn (): Opportunity => lifecycleOrderOpportunity(),
        fn () => fn (int $id) => OpportunityRevertedToQuotation::fire(opportunity_id: $id, reason: 'wrong confirm'),
    ],
    'reinstated' => [
        fn () => function (): Opportunity {
            $opportunity = lifecycleQuotationOpportunity();
            (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);

            return $opportunity->refresh();
        },
        fn () => fn (int $id) => OpportunityReinstated::fire(opportunity_id: $id, reason: 'customer returned'),
    ],
    'reopened' => [
        fn () => function (): Opportunity {
            $opportunity = lifecycleOrderOpportunity();
            (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::OrderComplete);

            return $opportunity->refresh();
        },
        fn () => fn (int $id) => OpportunityReopened::fire(opportunity_id: $id, reason: 'late adjustment'),
    ],
    'locks applied' => [
        fn () => fn (): Opportunity => lifecycleQuotationOpportunity(),
        fn () => fn (int $id) => OpportunityLocksApplied::fire(opportunity_id: $id, reason: 'freeze pricing'),
    ],
    'locks released' => [
        fn () => fn (): Opportunity => lifecycleOrderOpportunity(),
        fn () => fn (int $id) => OpportunityLocksReleased::fire(opportunity_id: $id, reason: 'reprice'),
    ],
    'status changed' => [
        fn () => fn (): Opportunity => lifecycleQuotationOpportunity(),
        fn () => fn (int $id) => OpportunityStatusChanged::fire(
            opportunity_id: $id,
            to_status: OpportunityStatus::QuotationReserved->statusValue(),
        ),
    ],
    'status promoted' => [
        fn () => fn (): Opportunity => lifecycleOrderOpportunity(),
        fn () => fn (int $id) => OpportunityStatusPromoted::fire(
            opportunity_id: $id,
            to_status: OpportunityStatus::OrderDispatched->statusValue(),
        ),
    ],
    'updated' => [
        fn () => fn (): Opportunity => lifecycleDraftOpportunity(),
        fn () => fn (int $id) => OpportunityUpdated::fire(
            opportunity_id: $id,
            provided: ['subject'],
            subject: 'Renamed subject',
        ),
    ],
    'deleted' => [
        fn () => fn (): Opportunity => lifecycleDraftOpportunity(),
        fn () => fn (int $id) => OpportunityDeleted::fire(opportunity_id: $id),
    ],
]);
