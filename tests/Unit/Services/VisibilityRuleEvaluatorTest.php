<?php

use App\Services\VisibilityRuleEvaluator;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->evaluator = new VisibilityRuleEvaluator;
});

// ─── Null/Empty Rules ──────────────────────────────────────────

it('returns true when rules are null', function () {
    expect($this->evaluator->evaluate(null, ['name' => 'Test']))->toBeTrue();
});

it('returns true when rules are empty array', function () {
    expect($this->evaluator->evaluate([], ['name' => 'Test']))->toBeTrue();
});

// ─── eq operator ───────────────────────────────────────────────

it('evaluates eq operator with matching string value', function () {
    $rules = [['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation']];

    expect($this->evaluator->evaluate($rules, ['membership_type' => 'Organisation']))->toBeTrue();
});

it('evaluates eq operator with non-matching string value', function () {
    $rules = [['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation']];

    expect($this->evaluator->evaluate($rules, ['membership_type' => 'Contact']))->toBeFalse();
});

it('evaluates eq operator with case-insensitive string comparison', function () {
    $rules = [['field' => 'name', 'operator' => 'eq', 'value' => 'ACME']];

    expect($this->evaluator->evaluate($rules, ['name' => 'acme']))->toBeTrue();
});

// ─── not_eq / neq operator ─────────────────────────────────────

it('evaluates not_eq operator with non-matching value', function () {
    $rules = [['field' => 'status', 'operator' => 'not_eq', 'value' => 'Active']];

    expect($this->evaluator->evaluate($rules, ['status' => 'Inactive']))->toBeTrue();
});

it('evaluates neq alias with matching value returns false', function () {
    $rules = [['field' => 'status', 'operator' => 'neq', 'value' => 'Active']];

    expect($this->evaluator->evaluate($rules, ['status' => 'Active']))->toBeFalse();
});

// ─── gt operator ───────────────────────────────────────────────

it('evaluates gt operator when actual is greater', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gt', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 15]))->toBeTrue();
});

it('evaluates gt operator when actual is equal returns false', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gt', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 10]))->toBeFalse();
});

it('evaluates gt operator when actual is less returns false', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gt', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 5]))->toBeFalse();
});

// ─── lt operator ───────────────────────────────────────────────

it('evaluates lt operator when actual is less', function () {
    $rules = [['field' => 'quantity', 'operator' => 'lt', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 5]))->toBeTrue();
});

it('evaluates lt operator when actual is equal returns false', function () {
    $rules = [['field' => 'quantity', 'operator' => 'lt', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 10]))->toBeFalse();
});

// ─── gte operator ──────────────────────────────────────────────

it('evaluates gte operator when actual is equal', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 10]))->toBeTrue();
});

it('evaluates gte operator when actual is greater', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 15]))->toBeTrue();
});

it('evaluates gte operator when actual is less returns false', function () {
    $rules = [['field' => 'quantity', 'operator' => 'gte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 5]))->toBeFalse();
});

// ─── lte operator ──────────────────────────────────────────────

it('evaluates lte operator when actual is equal', function () {
    $rules = [['field' => 'quantity', 'operator' => 'lte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 10]))->toBeTrue();
});

it('evaluates lte operator when actual is less', function () {
    $rules = [['field' => 'quantity', 'operator' => 'lte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 5]))->toBeTrue();
});

it('evaluates lte operator when actual is greater returns false', function () {
    $rules = [['field' => 'quantity', 'operator' => 'lte', 'value' => 10]];

    expect($this->evaluator->evaluate($rules, ['quantity' => 15]))->toBeFalse();
});

// ─── contains operator ─────────────────────────────────────────

it('evaluates contains operator with matching substring', function () {
    $rules = [['field' => 'name', 'operator' => 'contains', 'value' => 'Acme']];

    expect($this->evaluator->evaluate($rules, ['name' => 'Acme Corp']))->toBeTrue();
});

it('evaluates contains operator with non-matching substring', function () {
    $rules = [['field' => 'name', 'operator' => 'contains', 'value' => 'Xyz']];

    expect($this->evaluator->evaluate($rules, ['name' => 'Acme Corp']))->toBeFalse();
});

it('evaluates contains operator returns false when actual is not a string', function () {
    $rules = [['field' => 'count', 'operator' => 'contains', 'value' => '5']];

    expect($this->evaluator->evaluate($rules, ['count' => 5]))->toBeFalse();
});

// ─── in operator ───────────────────────────────────────────────

it('evaluates in operator when value is in array', function () {
    $rules = [['field' => 'status', 'operator' => 'in', 'value' => ['Active', 'Pending']]];

    expect($this->evaluator->evaluate($rules, ['status' => 'Active']))->toBeTrue();
});

it('evaluates in operator when value is not in array', function () {
    $rules = [['field' => 'status', 'operator' => 'in', 'value' => ['Active', 'Pending']]];

    expect($this->evaluator->evaluate($rules, ['status' => 'Closed']))->toBeFalse();
});

it('evaluates in operator returns false when expected is not an array', function () {
    $rules = [['field' => 'status', 'operator' => 'in', 'value' => 'Active']];

    expect($this->evaluator->evaluate($rules, ['status' => 'Active']))->toBeFalse();
});

// ─── not_in operator ───────────────────────────────────────────

it('evaluates not_in operator when value is not in array', function () {
    $rules = [['field' => 'status', 'operator' => 'not_in', 'value' => ['Closed', 'Cancelled']]];

    expect($this->evaluator->evaluate($rules, ['status' => 'Active']))->toBeTrue();
});

it('evaluates not_in operator when value is in array returns false', function () {
    $rules = [['field' => 'status', 'operator' => 'not_in', 'value' => ['Closed', 'Cancelled']]];

    expect($this->evaluator->evaluate($rules, ['status' => 'Closed']))->toBeFalse();
});

// ─── present / blank operators ─────────────────────────────────

it('evaluates present operator when value is present', function () {
    $rules = [['field' => 'name', 'operator' => 'present', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => 'Hello']))->toBeTrue();
});

it('evaluates present operator returns false for null', function () {
    $rules = [['field' => 'name', 'operator' => 'present', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => null]))->toBeFalse();
});

it('evaluates present operator returns false for empty string', function () {
    $rules = [['field' => 'name', 'operator' => 'present', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => '']))->toBeFalse();
});

it('evaluates blank operator when value is null', function () {
    $rules = [['field' => 'name', 'operator' => 'blank', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => null]))->toBeTrue();
});

it('evaluates blank operator when value is empty string', function () {
    $rules = [['field' => 'name', 'operator' => 'blank', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => '']))->toBeTrue();
});

it('evaluates blank operator returns false for non-empty value', function () {
    $rules = [['field' => 'name', 'operator' => 'blank', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['name' => 'Hello']))->toBeFalse();
});

// ─── AND logic (multiple rules) ────────────────────────────────

it('requires all rules to pass with AND logic', function () {
    $rules = [
        ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
        ['field' => 'is_active', 'operator' => 'eq', 'value' => true],
    ];

    expect($this->evaluator->evaluate($rules, [
        'membership_type' => 'Organisation',
        'is_active' => true,
    ]))->toBeTrue();
});

it('fails when one rule fails with AND logic', function () {
    $rules = [
        ['field' => 'membership_type', 'operator' => 'eq', 'value' => 'Organisation'],
        ['field' => 'is_active', 'operator' => 'eq', 'value' => true],
    ];

    expect($this->evaluator->evaluate($rules, [
        'membership_type' => 'Organisation',
        'is_active' => false,
    ]))->toBeFalse();
});

// ─── looseEquals edge cases ────────────────────────────────────

it('loose-equals string "1" vs int 1', function () {
    $rules = [['field' => 'count', 'operator' => 'eq', 'value' => 1]];

    expect($this->evaluator->evaluate($rules, ['count' => '1']))->toBeTrue();
});

it('loose-equals bool true with int 1', function () {
    $rules = [['field' => 'is_active', 'operator' => 'eq', 'value' => true]];

    expect($this->evaluator->evaluate($rules, ['is_active' => 1]))->toBeTrue();
});

it('loose-equals bool false with int 0', function () {
    $rules = [['field' => 'is_active', 'operator' => 'eq', 'value' => false]];

    expect($this->evaluator->evaluate($rules, ['is_active' => 0]))->toBeTrue();
});

it('loose-equals returns false for non-matching types without coercion', function () {
    $rules = [['field' => 'name', 'operator' => 'eq', 'value' => 42]];

    expect($this->evaluator->evaluate($rules, ['name' => 'forty-two']))->toBeFalse();
});

it('loose-equals null vs null returns true', function () {
    $rules = [['field' => 'value', 'operator' => 'eq', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['value' => null]))->toBeTrue();
});

it('loose-equals bool actual compared to non-bool expected', function () {
    $rules = [['field' => 'flag', 'operator' => 'eq', 'value' => 1]];

    expect($this->evaluator->evaluate($rules, ['flag' => true]))->toBeTrue();
});

// ─── filterVisible method ──────────────────────────────────────

it('filters visible fields from collection', function () {
    $fields = [
        ['name' => 'field_a', 'visibility_rules' => [['field' => 'type', 'operator' => 'eq', 'value' => 'A']]],
        ['name' => 'field_b', 'visibility_rules' => [['field' => 'type', 'operator' => 'eq', 'value' => 'B']]],
        ['name' => 'field_c', 'visibility_rules' => null],
    ];

    $result = $this->evaluator->filterVisible($fields, ['type' => 'A']);

    expect($result)->toHaveCount(2);
    expect($result[0]['name'])->toBe('field_a');
    expect($result[1]['name'])->toBe('field_c');
});

it('filterVisible returns all fields when no visibility rules', function () {
    $fields = [
        ['name' => 'field_a'],
        ['name' => 'field_b', 'visibility_rules' => null],
        ['name' => 'field_c', 'visibility_rules' => []],
    ];

    $result = $this->evaluator->filterVisible($fields, ['anything' => 'value']);

    expect($result)->toHaveCount(3);
});

// ─── Unknown operator fallback ─────────────────────────────────

it('returns true and logs warning for unknown operator', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message) {
            return str_contains($message, 'Unknown visibility rule operator: banana');
        });

    $rules = [['field' => 'name', 'operator' => 'banana', 'value' => 'test']];

    expect($this->evaluator->evaluate($rules, ['name' => 'whatever']))->toBeTrue();
});

// ─── Missing field key ─────────────────────────────────────────

it('returns true and logs warning when rule is missing field key', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message) {
            return str_contains($message, 'Visibility rule missing field key');
        });

    $rules = [['operator' => 'eq', 'value' => 'test']];

    expect($this->evaluator->evaluate($rules, ['name' => 'whatever']))->toBeTrue();
});

// ─── Missing field in entity data ──────────────────────────────

it('evaluates eq against null when field is missing from entity data', function () {
    $rules = [['field' => 'missing_field', 'operator' => 'eq', 'value' => 'something']];

    expect($this->evaluator->evaluate($rules, ['other' => 'data']))->toBeFalse();
});

it('evaluates blank as true when field is missing from entity data', function () {
    $rules = [['field' => 'missing_field', 'operator' => 'blank', 'value' => null]];

    expect($this->evaluator->evaluate($rules, ['other' => 'data']))->toBeTrue();
});

// ─── Numeric comparison edge cases ─────────────────────────────

it('gt returns false when actual is not numeric', function () {
    $rules = [['field' => 'name', 'operator' => 'gt', 'value' => 5]];

    expect($this->evaluator->evaluate($rules, ['name' => 'abc']))->toBeFalse();
});

it('lt returns false when expected is not numeric', function () {
    $rules = [['field' => 'qty', 'operator' => 'lt', 'value' => 'abc']];

    expect($this->evaluator->evaluate($rules, ['qty' => 5]))->toBeFalse();
});
