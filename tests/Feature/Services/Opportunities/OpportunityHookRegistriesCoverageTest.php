<?php

use App\Guards\Opportunities\Contracts\TransitionRule;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Services\Opportunities\Hooks\ApprovalChainRegistry;
use App\Services\Opportunities\Hooks\WorkflowTriggerRegistry;
use App\Services\Opportunities\TransitionRuleRegistry;

it('registers, looks up and lists approval chains by transition', function () {
    $registry = new ApprovalChainRegistry;

    expect($registry->requiresApproval('opportunity.converted_to_order'))->toBeFalse()
        ->and($registry->forTransition('opportunity.converted_to_order'))->toBeNull();

    $registry->register('opportunity.converted_to_order', ['chain' => 'finance']);

    expect($registry->requiresApproval('opportunity.converted_to_order'))->toBeTrue()
        ->and($registry->forTransition('opportunity.converted_to_order'))->toBe(['chain' => 'finance'])
        ->and($registry->all())->toBe(['opportunity.converted_to_order' => ['chain' => 'finance']]);
});

it('binds multiple workflow triggers per transition in registration order', function () {
    $registry = new WorkflowTriggerRegistry;

    expect($registry->has('opportunity.converted_to_order'))->toBeFalse()
        ->and($registry->forTransition('opportunity.converted_to_order'))->toBe([]);

    $registry->register('opportunity.converted_to_order', 'notify-ops');
    $registry->register('opportunity.converted_to_order', 'create-task');

    expect($registry->has('opportunity.converted_to_order'))->toBeTrue()
        ->and($registry->forTransition('opportunity.converted_to_order'))->toBe(['notify-ops', 'create-task'])
        ->and($registry->all())->toBe(['opportunity.converted_to_order' => ['notify-ops', 'create-task']]);
});

it('registers transition rules keyed by key and lists them', function () {
    $registry = new TransitionRuleRegistry;

    $rule = new class implements TransitionRule
    {
        public function key(): string
        {
            return 'demo.rule';
        }

        public function appliesTo(TransitionContext $context): bool
        {
            return true;
        }

        public function evaluate(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }

        public function precheck(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }
    };

    expect($registry->has('demo.rule'))->toBeFalse();

    $registry->register($rule);

    expect($registry->has('demo.rule'))->toBeTrue()
        ->and($registry->all())->toBe(['demo.rule' => $rule]);
});

it('filters rules applicable to a transition context in registration order', function () {
    $registry = new TransitionRuleRegistry;

    $applies = new class implements TransitionRule
    {
        public function key(): string
        {
            return 'applies';
        }

        public function appliesTo(TransitionContext $context): bool
        {
            return true;
        }

        public function evaluate(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }

        public function precheck(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }
    };

    $skips = new class implements TransitionRule
    {
        public function key(): string
        {
            return 'skips';
        }

        public function appliesTo(TransitionContext $context): bool
        {
            return false;
        }

        public function evaluate(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }

        public function precheck(TransitionContext $context): GuardResult
        {
            return GuardResult::allow();
        }
    };

    $registry->register($applies);
    $registry->register($skips);

    $context = new TransitionContext(
        transition: 'opportunity.test',
        opportunity: Opportunity::factory()->create(),
    );

    $applicable = $registry->applicableTo($context);

    expect($applicable)->toHaveCount(1)
        ->and($applicable[0]->key())->toBe('applies');
});
