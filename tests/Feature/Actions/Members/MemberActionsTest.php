<?php

use App\Actions\Members\CreateMember;
use App\Actions\Members\DeleteMember;
use App\Actions\Members\UpdateMember;
use App\Data\Members\CreateMemberData;
use App\Data\Members\UpdateMemberData;
use App\Enums\MembershipType;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a member', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateMemberData::from([
        'name' => 'Acme Corp',
        'membership_type' => MembershipType::Organisation->value,
    ]);

    $result = (new CreateMember)($data);

    expect($result->name)->toBe('Acme Corp');
    expect($result->membership_type)->toBe('organisation');
    expect($result->is_active)->toBeTrue();
    expect(Member::where('name', 'Acme Corp')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('creates a member with custom fields', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateMemberData::from([
        'name' => 'Custom Corp',
        'membership_type' => MembershipType::Organisation->value,
        'custom_fields' => ['po_reference' => 'PO-123'],
    ]);

    $result = (new CreateMember)($data);

    expect($result->name)->toBe('Custom Corp');
});

it('rejects unauthorized member creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateMemberData::from([
        'name' => 'Unauthorized',
        'membership_type' => MembershipType::Contact->value,
    ]);

    (new CreateMember)($data);
})->throws(AuthorizationException::class);

it('updates a member', function () {
    Event::fake([AuditableEvent::class]);

    $member = Member::factory()->organisation()->create(['name' => 'Old Name']);

    $data = UpdateMemberData::from([
        'name' => 'New Name',
    ]);

    $result = (new UpdateMember)($member, $data);

    expect($result->name)->toBe('New Name');
    expect($member->fresh()->name)->toBe('New Name');

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a member with custom fields', function () {
    Event::fake([AuditableEvent::class]);

    $customField = \App\Models\CustomField::factory()->create([
        'name' => 'po_reference',
        'module_type' => 'Member',
        'field_type' => \App\Enums\CustomFieldType::String,
    ]);

    $member = Member::factory()->create(['name' => 'Test Corp']);

    $data = UpdateMemberData::from([
        'name' => 'Updated Corp',
        'custom_fields' => ['po_reference' => 'PO-999'],
    ]);

    $result = (new UpdateMember)($member, $data);

    expect($result->name)->toBe('Updated Corp');

    $cfv = \App\Models\CustomFieldValue::query()
        ->where('custom_field_id', $customField->id)
        ->where('entity_type', Member::class)
        ->where('entity_id', $member->id)
        ->first();

    expect($cfv)->not->toBeNull()
        ->and($cfv->value_string)->toBe('PO-999');
});

it('rejects unauthorized member update', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $member = Member::factory()->create();

    $data = UpdateMemberData::from(['name' => 'Hack']);

    (new UpdateMember)($member, $data);
})->throws(AuthorizationException::class);

it('deletes a member via soft delete', function () {
    Event::fake([AuditableEvent::class]);

    $member = Member::factory()->create();

    (new DeleteMember)($member);

    expect(Member::find($member->id))->toBeNull();
    expect(Member::withTrashed()->find($member->id))->not->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized member deletion', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $member = Member::factory()->create();

    (new DeleteMember)($member);
})->throws(AuthorizationException::class);
