<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\Rules\FxTaxLockRule;
use App\Guards\Opportunities\Stages\PermissionStage;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Queue::fake();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * A quotation carrying a single manual line, created as the given actor.
 */
function actionsQuotation(User $actor, Store $store): Opportunity
{
    Auth::login($actor);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Available actions',
        'store_id' => $store->id,
        'starts_at' => '2026-12-01T09:00:00Z',
        'ends_at' => '2026-12-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    return $opportunity->refresh();
}

/**
 * Pluck a single action's verdict from the endpoint payload.
 *
 * @param  array<int, array<string, mixed>>  $actions
 * @return array<string, mixed>
 */
function actionByKey(array $actions, string $key): array
{
    foreach ($actions as $action) {
        if ($action['key'] === $key) {
            return $action;
        }
    }

    return [];
}

// ---------------------------------------------------------------------------
// available_actions endpoint
// ---------------------------------------------------------------------------

it('enumerates the action set with allowed flags for a quotation', function () {
    $opportunity = actionsQuotation($this->owner, $this->store);

    $token = $this->owner->createToken('test', ['opportunities:read'])->plainTextToken;
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
        ->assertOk();

    $actions = $response->json('available_actions');
    $keys = array_column($actions, 'key');

    expect($keys)->toContain('convert_to_quotation', 'convert_to_order', 'change_status', 'reinstate', 'revert_to_quotation', 'unlock_locks', 'clone', 'dispatch', 'delete');

    // A quotation CAN convert to order, but cannot convert to quotation again
    // (not a draft), cannot reinstate (active), cannot revert (not an order).
    expect(actionByKey($actions, 'convert_to_order')['allowed'])->toBeTrue()
        ->and(actionByKey($actions, 'convert_to_quotation')['allowed'])->toBeFalse()
        ->and(actionByKey($actions, 'convert_to_quotation')['code'])->toBe('invalid_state')
        ->and(actionByKey($actions, 'reinstate')['allowed'])->toBeFalse()
        ->and(actionByKey($actions, 'revert_to_quotation')['allowed'])->toBeFalse();
});

it('reports fx_tax_locked is unlockable and revert/reinstate verdicts for an order', function () {
    $opportunity = actionsQuotation($this->owner, $this->store);
    (new ConvertToOrder)($opportunity->refresh());

    $token = $this->owner->createToken('test', ['opportunities:read'])->plainTextToken;
    $actions = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
        ->assertOk()
        ->json('available_actions');

    // An undispatched order can be reverted; the locks can be released.
    expect(actionByKey($actions, 'revert_to_quotation')['allowed'])->toBeTrue()
        ->and(actionByKey($actions, 'unlock_locks')['allowed'])->toBeTrue()
        ->and(actionByKey($actions, 'convert_to_order')['allowed'])->toBeFalse();
});

it('reports reinstate as allowed for a lost quotation', function () {
    $opportunity = actionsQuotation($this->owner, $this->store);
    (new ChangeOpportunityStatus)($opportunity, OpportunityStatus::QuotationLost);

    $token = $this->owner->createToken('test', ['opportunities:read'])->plainTextToken;
    $actions = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
        ->assertOk()
        ->json('available_actions');

    expect(actionByKey($actions, 'reinstate')['allowed'])->toBeTrue()
        ->and(actionByKey($actions, 'change_status')['allowed'])->toBeFalse()
        ->and(actionByKey($actions, 'change_status')['code'])->toBe('invalid_state');
});

it('marks a privileged action denied for a permission-restricted actor', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');
    $opportunity = actionsQuotation($sales, $this->store);
    (new ConvertToOrder)($opportunity->refresh());

    expect($sales->can('opportunities.unlock_rates'))->toBeFalse();

    $token = $sales->createToken('test', ['opportunities:read'])->plainTextToken;
    $actions = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
        ->assertOk()
        ->json('available_actions');

    expect(actionByKey($actions, 'unlock_locks')['allowed'])->toBeFalse()
        ->and(actionByKey($actions, 'unlock_locks')['code'])->toBe('permission_denied');
});

it('requires the opportunities:read ability', function () {
    $opportunity = actionsQuotation($this->owner, $this->store);

    // The actionsQuotation helper logs the actor in via the session guard to
    // build the quote; clear it so the request authenticates solely via the
    // bearer token and the Sanctum ability check (not the session) is exercised.
    Auth::logout();

    $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$opportunity->id}/available_actions")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// GuardPipeline::check() parity with run()
// ---------------------------------------------------------------------------

it('check() returns an allow for a transition that run() permits', function () {
    Auth::login($this->owner);
    $opportunity = actionsQuotation($this->owner, $this->store);

    $context = new TransitionContext(
        transition: 'opportunity.change_status',
        opportunity: $opportunity->refresh(),
        permission: 'opportunities.edit',
    );

    $result = app(GuardPipeline::class)->check($context);

    expect($result)->toBeInstanceOf(GuardResult::class)
        ->and($result->allowed)->toBeTrue();

    // Parity: run() does not throw for the same context (an uncaught throw fails the test).
    app(GuardPipeline::class)->run($context);
});

it('check() returns a fx_tax_locked deny where run() throws, with no side effects', function () {
    Auth::login($this->owner);
    $opportunity = actionsQuotation($this->owner, $this->store);
    (new ConvertToOrder)($opportunity->refresh());

    $context = new TransitionContext(
        transition: 'opportunity.item.price_override',
        opportunity: $opportunity->refresh(),
        permission: 'opportunities.edit',
        changes: ['changes_rate' => true],
    );

    // check() reports the denial with the machine-readable code; it does not throw.
    $result = app(GuardPipeline::class)->check($context);
    expect($result->denied())->toBeTrue()
        ->and($result->code)->toBe(FxTaxLockRule::CODE);

    // run() throws for the same context — parity on the verdict.
    expect(fn () => app(GuardPipeline::class)->run($context))->toThrow(ValidationException::class);
});

it('check() returns permission_denied without throwing where run() raises a 403', function () {
    $sales = User::factory()->create();
    $sales->assignRole('Sales');
    $opportunity = actionsQuotation($sales, $this->store);
    (new ConvertToOrder)($opportunity->refresh());

    Auth::login($sales);

    $context = new TransitionContext(
        transition: 'opportunity.unlock',
        opportunity: $opportunity->refresh(),
        permission: 'opportunities.unlock_rates',
    );

    // check() reports permission_denied (Gate::allows), no throw.
    $result = app(GuardPipeline::class)->check($context);
    expect($result->denied())->toBeTrue()
        ->and($result->code)->toBe(PermissionStage::CODE);

    // run() raises the AuthorizationException for the same context.
    expect(fn () => app(GuardPipeline::class)->run($context))->toThrow(AuthorizationException::class);
});
