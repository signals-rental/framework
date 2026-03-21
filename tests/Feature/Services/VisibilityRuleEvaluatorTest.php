<?php

use App\Services\VisibilityRuleEvaluator;

describe('VisibilityRuleEvaluator', function () {
    it('returns true when rules are null', function () {
        $evaluator = new VisibilityRuleEvaluator;
        expect($evaluator->evaluate(null, ['name' => 'Test']))->toBeTrue();
    });

    it('returns true when rules are empty', function () {
        $evaluator = new VisibilityRuleEvaluator;
        expect($evaluator->evaluate([], ['name' => 'Test']))->toBeTrue();
    });

    it('evaluates eq operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation']];

        expect($evaluator->evaluate($rules, ['membership_type' => 'Organisation']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['membership_type' => 'Contact']))->toBeFalse();
    });

    it('evaluates eq operator case-insensitively for strings', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'status', 'operator' => 'eq', 'value' => 'active']];

        expect($evaluator->evaluate($rules, ['status' => 'Active']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['status' => 'ACTIVE']))->toBeTrue();
    });

    it('evaluates not_eq operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'type', 'operator' => 'not_eq', 'value' => 'Venue']];

        expect($evaluator->evaluate($rules, ['type' => 'Organisation']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['type' => 'Venue']))->toBeFalse();
    });

    it('evaluates neq as alias for not_eq', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'type', 'operator' => 'neq', 'value' => 'Venue']];

        expect($evaluator->evaluate($rules, ['type' => 'Organisation']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['type' => 'Venue']))->toBeFalse();
    });

    it('evaluates in operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'status', 'operator' => 'in', 'value' => ['active', 'pending']]];

        expect($evaluator->evaluate($rules, ['status' => 'active']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['status' => 'pending']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['status' => 'closed']))->toBeFalse();
    });

    it('evaluates not_in operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'status', 'operator' => 'not_in', 'value' => ['archived', 'deleted']]];

        expect($evaluator->evaluate($rules, ['status' => 'active']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['status' => 'archived']))->toBeFalse();
    });

    it('evaluates present operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'email', 'operator' => 'present']];

        expect($evaluator->evaluate($rules, ['email' => 'test@example.com']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['email' => null]))->toBeFalse();
        expect($evaluator->evaluate($rules, ['email' => '']))->toBeFalse();
        expect($evaluator->evaluate($rules, []))->toBeFalse();
    });

    it('evaluates blank operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'notes', 'operator' => 'blank']];

        expect($evaluator->evaluate($rules, ['notes' => null]))->toBeTrue();
        expect($evaluator->evaluate($rules, ['notes' => '']))->toBeTrue();
        expect($evaluator->evaluate($rules, []))->toBeTrue();
        expect($evaluator->evaluate($rules, ['notes' => 'something']))->toBeFalse();
    });

    it('evaluates numeric comparison operators', function () {
        $evaluator = new VisibilityRuleEvaluator;

        expect($evaluator->evaluate([['field' => 'rating', 'operator' => 'gt', 'value' => 3]], ['rating' => 5]))->toBeTrue();
        expect($evaluator->evaluate([['field' => 'rating', 'operator' => 'gt', 'value' => 5]], ['rating' => 5]))->toBeFalse();
        expect($evaluator->evaluate([['field' => 'rating', 'operator' => 'gte', 'value' => 5]], ['rating' => 5]))->toBeTrue();
        expect($evaluator->evaluate([['field' => 'rating', 'operator' => 'lt', 'value' => 10]], ['rating' => 5]))->toBeTrue();
        expect($evaluator->evaluate([['field' => 'rating', 'operator' => 'lte', 'value' => 5]], ['rating' => 5]))->toBeTrue();
    });

    it('evaluates contains operator', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'name', 'operator' => 'contains', 'value' => 'Ltd']];

        expect($evaluator->evaluate($rules, ['name' => 'Acme Ltd']))->toBeTrue();
        expect($evaluator->evaluate($rules, ['name' => 'Acme Inc']))->toBeFalse();
    });

    it('evaluates boolean values correctly', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'is_active', 'operator' => 'eq', 'value' => true]];

        expect($evaluator->evaluate($rules, ['is_active' => true]))->toBeTrue();
        expect($evaluator->evaluate($rules, ['is_active' => 1]))->toBeTrue();
        expect($evaluator->evaluate($rules, ['is_active' => false]))->toBeFalse();
    });

    it('uses AND logic for multiple rules', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [
            ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
            ['field' => 'is_active', 'operator' => 'eq', 'value' => true],
        ];

        expect($evaluator->evaluate($rules, ['membership_type' => 'Organisation', 'is_active' => true]))->toBeTrue();
        expect($evaluator->evaluate($rules, ['membership_type' => 'Organisation', 'is_active' => false]))->toBeFalse();
        expect($evaluator->evaluate($rules, ['membership_type' => 'Contact', 'is_active' => true]))->toBeFalse();
    });

    it('treats unknown operators as always true', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['field' => 'name', 'operator' => 'unknown_op', 'value' => 'test']];

        expect($evaluator->evaluate($rules, ['name' => 'test']))->toBeTrue();
    });

    it('treats rules without field as always true', function () {
        $evaluator = new VisibilityRuleEvaluator;
        $rules = [['operator' => 'eq', 'value' => 'test']];

        expect($evaluator->evaluate($rules, ['name' => 'test']))->toBeTrue();
    });

    it('returns false for numeric operators with non-numeric values', function () {
        $evaluator = new VisibilityRuleEvaluator;

        expect($evaluator->evaluate([['field' => 'name', 'operator' => 'gt', 'value' => 5]], ['name' => 'text']))->toBeFalse();
        expect($evaluator->evaluate([['field' => 'name', 'operator' => 'gte', 'value' => 5]], ['name' => 'text']))->toBeFalse();
        expect($evaluator->evaluate([['field' => 'name', 'operator' => 'lt', 'value' => 5]], ['name' => 'text']))->toBeFalse();
        expect($evaluator->evaluate([['field' => 'name', 'operator' => 'lte', 'value' => 5]], ['name' => 'text']))->toBeFalse();
    });

    it('returns false for contains with non-string values', function () {
        $evaluator = new VisibilityRuleEvaluator;

        expect($evaluator->evaluate([['field' => 'count', 'operator' => 'contains', 'value' => 'x']], ['count' => 42]))->toBeFalse();
    });

    it('returns false for in with non-array expected value', function () {
        $evaluator = new VisibilityRuleEvaluator;

        expect($evaluator->evaluate([['field' => 'status', 'operator' => 'in', 'value' => 'active']], ['status' => 'active']))->toBeFalse();
    });

    it('defaults operator to eq when not specified', function () {
        $evaluator = new VisibilityRuleEvaluator;

        expect($evaluator->evaluate([['field' => 'name', 'value' => 'test']], ['name' => 'test']))->toBeTrue();
        expect($evaluator->evaluate([['field' => 'name', 'value' => 'test']], ['name' => 'other']))->toBeFalse();
    });

    it('filters visible fields', function () {
        $evaluator = new VisibilityRuleEvaluator;

        $fields = [
            ['name' => 'always_visible', 'visibility_rules' => null],
            ['name' => 'conditional', 'visibility_rules' => [['field' => 'type', 'operator' => 'eq', 'value' => 'A']]],
            ['name' => 'hidden', 'visibility_rules' => [['field' => 'type', 'operator' => 'eq', 'value' => 'B']]],
        ];

        $visible = $evaluator->filterVisible($fields, ['type' => 'A']);

        expect($visible)->toHaveCount(2);
        expect($visible[0]['name'])->toBe('always_visible');
        expect($visible[1]['name'])->toBe('conditional');
    });
});
