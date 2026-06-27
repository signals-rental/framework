<?php

use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\PluginValidatorRegistry;
use App\Guards\Opportunities\Rules\DispatchShortageRule;
use App\Guards\Opportunities\Rules\FxTaxLockRule;
use App\Guards\Opportunities\Stages\PermissionStage;
use App\Guards\Opportunities\Stages\PluginValidatorStage;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/**
 * A minimal stub TransitionRule whose verdicts the tests control directly, so the
 * stage/registry plumbing can be exercised without a concrete core rule.
 */
function stubValidator(string $key, bool $applies, GuardResult $verdict): TransitionRule
{
    return new class($key, $applies, $verdict) implements TransitionRule
    {
        public function __construct(
            private string $ruleKey,
            private bool $applies,
            private GuardResult $verdict,
        ) {}

        public function key(): string
        {
            return $this->ruleKey;
        }

        public function appliesTo(TransitionContext $context): bool
        {
            return $this->applies;
        }

        public function evaluate(TransitionContext $context): GuardResult
        {
            return $this->verdict;
        }

        public function precheck(TransitionContext $context): GuardResult
        {
            return $this->verdict;
        }
    };
}

// ---------------------------------------------------------------------------
// GuardResult — firstError() over a deny's field errors + the stage fallback
// ---------------------------------------------------------------------------

it('returns the first field message of a denial via firstError()', function () {
    $denied = GuardResult::deny('business_rules', [
        'tax' => ['The tax figures are locked on a confirmed order; release the locks before changing tax.'],
    ], 'fx_tax_locked');

    expect($denied->denied())->toBeTrue()
        ->and($denied->firstError())->toBe('The tax figures are locked on a confirmed order; release the locks before changing tax.');
});

it('falls back to a stage-named message when a denial carries no field messages', function () {
    // Empty errors map (and an empty message list) → the generic stage fallback.
    $denied = GuardResult::deny('permission', ['permission' => []]);

    expect($denied->firstError())->toBe('This transition was blocked at the permission stage.');
});

it('returns null from firstError() on an allow', function () {
    expect(GuardResult::allow()->firstError())->toBeNull();
});

// ---------------------------------------------------------------------------
// PluginValidatorRegistry — register + has
// ---------------------------------------------------------------------------

it('registers a validator keyed by its key() and reports it via has()', function () {
    $registry = new PluginValidatorRegistry;
    $validator = stubValidator('demo_validator', true, GuardResult::allow());

    expect($registry->has('demo_validator'))->toBeFalse();

    $registry->register($validator);

    expect($registry->has('demo_validator'))->toBeTrue()
        ->and($registry->has('nope'))->toBeFalse()
        ->and($registry->all())->toBe(['demo_validator' => $validator]);
});

// ---------------------------------------------------------------------------
// PluginValidatorStage — evaluate + precheck over registered validators
// ---------------------------------------------------------------------------

it('allows when every applicable plugin validator allows (evaluate)', function () {
    $registry = new PluginValidatorRegistry;
    $registry->register(stubValidator('ok', true, GuardResult::allow()));
    $stage = new PluginValidatorStage($registry);

    $context = new TransitionContext('opportunity.edit', Opportunity::factory()->create());

    expect($stage->evaluate($context)->denied())->toBeFalse();
});

it('returns the first denying plugin validator verdict (evaluate)', function () {
    $registry = new PluginValidatorRegistry;
    $deny = GuardResult::deny('business_rules', ['x' => ['blocked by plugin']], 'plugin_block');
    $registry->register(stubValidator('blocker', true, $deny));
    $stage = new PluginValidatorStage($registry);

    $context = new TransitionContext('opportunity.edit', Opportunity::factory()->create());

    $result = $stage->evaluate($context);
    expect($result->denied())->toBeTrue()
        ->and($result->code)->toBe('plugin_block');
});

it('allows when applicable plugin validators allow (precheck)', function () {
    $registry = new PluginValidatorRegistry;
    $registry->register(stubValidator('ok', true, GuardResult::allow()));
    $stage = new PluginValidatorStage($registry);

    $context = new TransitionContext('opportunity.edit', Opportunity::factory()->create());

    expect($stage->precheck($context)->denied())->toBeFalse();
});

it('returns the first denying plugin validator verdict (precheck)', function () {
    $registry = new PluginValidatorRegistry;
    $deny = GuardResult::deny('business_rules', ['x' => ['blocked by plugin']], 'plugin_block');
    $registry->register(stubValidator('blocker', true, $deny));
    $stage = new PluginValidatorStage($registry);

    $context = new TransitionContext('opportunity.edit', Opportunity::factory()->create());

    expect($stage->precheck($context)->denied())->toBeTrue();
});

// ---------------------------------------------------------------------------
// PermissionStage — null permission no-op (evaluate) + denied authorize
// ---------------------------------------------------------------------------

it('allows in evaluate() when the context declares no permission', function () {
    $stage = new PermissionStage;
    $context = new TransitionContext('opportunity.system', Opportunity::factory()->create(), permission: null);

    expect($stage->evaluate($context)->denied())->toBeFalse();
});

it('throws an AuthorizationException in evaluate() when the actor lacks the permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Read Only');
    $this->actingAs($viewer);

    $stage = new PermissionStage;
    $context = new TransitionContext(
        'opportunity.edit',
        Opportunity::factory()->create(),
        permission: 'opportunities.delete',
    );

    expect($viewer->can('opportunities.delete'))->toBeFalse();

    expect(fn () => $stage->evaluate($context))->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------------
// FxTaxLockRule — evaluate() rate + tax deny branches
// ---------------------------------------------------------------------------

it('denies a rate-changing transition while the exchange rate is locked', function () {
    $opportunity = Opportunity::factory()->create(['exchange_rate_locked' => true, 'tax_locked' => false]);
    $rule = new FxTaxLockRule;

    $context = new TransitionContext('opportunity.edit', $opportunity, changes: ['changes_rate' => true]);

    expect($rule->appliesTo($context))->toBeTrue();

    $result = $rule->evaluate($context);
    expect($result->denied())->toBeTrue()
        ->and($result->code)->toBe(FxTaxLockRule::CODE)
        ->and($result->firstError())->toContain('exchange rate is locked');
});

it('denies a tax-changing transition while tax is locked', function () {
    $opportunity = Opportunity::factory()->create(['exchange_rate_locked' => false, 'tax_locked' => true]);
    $rule = new FxTaxLockRule;

    $context = new TransitionContext('opportunity.edit', $opportunity, changes: ['changes_tax' => true]);

    $result = $rule->evaluate($context);
    expect($result->denied())->toBeTrue()
        ->and($result->code)->toBe(FxTaxLockRule::CODE)
        ->and($result->firstError())->toContain('tax figures are locked');
});

it('allows a rate/tax change when neither lock is set (precheck mirrors evaluate)', function () {
    $opportunity = Opportunity::factory()->create(['exchange_rate_locked' => false, 'tax_locked' => false]);
    $rule = new FxTaxLockRule;

    $context = new TransitionContext('opportunity.edit', $opportunity, changes: ['changes_rate' => true, 'changes_tax' => true]);

    expect($rule->precheck($context)->denied())->toBeFalse();
});

// ---------------------------------------------------------------------------
// DispatchShortageRule — evaluate() is a deliberate no-op allow
// ---------------------------------------------------------------------------

it('makes DispatchShortageRule::evaluate() a no-op allow (write-time enforcement is per-line)', function () {
    $rule = app(DispatchShortageRule::class);
    $context = new TransitionContext(DispatchShortageRule::TRANSITION, Opportunity::factory()->create());

    expect($rule->appliesTo($context))->toBeTrue()
        ->and($rule->evaluate($context)->denied())->toBeFalse();
});
