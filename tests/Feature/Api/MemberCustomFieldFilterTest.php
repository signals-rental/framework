<?php

use App\Models\CustomField;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * End-to-end coverage for custom-field Ransack filtering through the HTTP API.
 *
 * The unit-level RansackFilterCustomFieldTest only asserts the generated SQL shape
 * (via toSql), so it never proves real rows are matched/excluded — and would not
 * catch a morph-type mismatch between how values are written and how they are
 * filtered. These tests run actual rows through the members endpoint.
 */
describe('GET /api/v1/members custom-field Ransack filtering', function () {
    it('filters members by a custom field using the cf. field-name predicate', function () {
        $field = CustomField::factory()->forModule('Member')->string()->create([
            'name' => 'po_reference',
            'is_searchable' => true,
            'is_active' => true,
        ]);
        $match = Member::factory()->create(['name' => 'Acme Ltd']);
        $other = Member::factory()->create(['name' => 'Globex Inc']);
        $match->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-123']);
        $other->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-999']);

        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?'.http_build_query(['q' => ['cf.po_reference_eq' => 'PO-123']]))
            ->assertOk();

        expect($response->json('members'))->toHaveCount(1)
            ->and($response->json('members.0.id'))->toBe($match->id);
    });

    it('filters members by a custom field using the numeric field-id predicate', function () {
        $field = CustomField::factory()->forModule('Member')->string()->create([
            'name' => 'po_reference',
            'is_searchable' => true,
            'is_active' => true,
        ]);
        $match = Member::factory()->create();
        Member::factory()->create();
        $match->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-123']);

        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?'.http_build_query(['q' => ["cf.{$field->id}_eq" => 'PO-123']]))
            ->assertOk();

        expect($response->json('members'))->toHaveCount(1)
            ->and($response->json('members.0.id'))->toBe($match->id);
    });

    it('ignores a cf. predicate on a non-searchable custom field', function () {
        $field = CustomField::factory()->forModule('Member')->string()->create([
            'name' => 'secret_ref',
            'is_searchable' => false,
            'is_active' => true,
        ]);
        $a = Member::factory()->create();
        $b = Member::factory()->create();
        $a->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-123']);
        $b->customFieldValues()->create(['custom_field_id' => $field->id, 'value_string' => 'PO-999']);

        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/members?'.http_build_query(['q' => ['cf.secret_ref_eq' => 'PO-123']]))
            ->assertOk();

        // A non-searchable field cannot be filtered on, so the predicate is a no-op
        // and both members remain in the result set.
        /** @var list<array{id: int}> $members */
        $members = $response->json('members');
        $ids = collect($members)->pluck('id');
        expect($ids)->toContain($a->id)
            ->and($ids)->toContain($b->id);
    });
});
