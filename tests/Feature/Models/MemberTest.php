<?php

use App\Enums\MembershipType;
use App\Models\Country;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Membership;
use App\Models\OrganisationTaxClass;
use App\Models\Store;
use App\Models\User;

it('creates a member with contact type', function () {
    $member = Member::factory()->contact()->create(['name' => 'John Doe']);

    expect($member->name)->toBe('John Doe')
        ->and($member->membership_type)->toBe(MembershipType::Contact)
        ->and($member->is_active)->toBeTrue();
});

it('creates a member with organisation type', function () {
    $member = Member::factory()->organisation()->create();

    expect($member->membership_type)->toBe(MembershipType::Organisation);
});

it('creates a member with venue type', function () {
    $member = Member::factory()->venue()->create();

    expect($member->membership_type)->toBe(MembershipType::Venue);
});

it('creates a member with user type', function () {
    $member = Member::factory()->user()->create();

    expect($member->membership_type)->toBe(MembershipType::User);
});

it('creates an inactive member', function () {
    $member = Member::factory()->inactive()->create();

    expect($member->is_active)->toBeFalse();
});

it('soft deletes a member', function () {
    $member = Member::factory()->create();
    $member->delete();

    expect(Member::query()->count())->toBe(0)
        ->and(Member::withTrashed()->count())->toBe(1);
});

it('scopes members by type', function () {
    Member::factory()->contact()->create();
    Member::factory()->organisation()->create();
    Member::factory()->contact()->create();

    expect(Member::query()->ofType(MembershipType::Contact)->count())->toBe(2)
        ->and(Member::query()->ofType(MembershipType::Organisation)->count())->toBe(1);
});

it('scopes members by active status', function () {
    Member::factory()->create();
    Member::factory()->inactive()->create();

    expect(Member::query()->active()->count())->toBe(1);
});

it('has memberships relationship', function () {
    $member = Member::factory()->create();
    Membership::factory()->create(['member_id' => $member->id]);

    expect($member->memberships)->toHaveCount(1);
});

it('creates a membership with factory', function () {
    $membership = Membership::factory()->create();

    expect($membership->member)->toBeInstanceOf(Member::class)
        ->and($membership->is_active)->toBeTrue();
});

it('creates an owner membership', function () {
    $membership = Membership::factory()->owner()->create();

    expect($membership->is_owner)->toBeTrue();
});

it('creates a membership for a store', function () {
    $store = Store::factory()->create();
    $membership = Membership::factory()->forStore($store)->create();

    expect($membership->store->id)->toBe($store->id);
});

it('cascades member delete to memberships', function () {
    $member = Member::factory()->create();
    Membership::factory()->create(['member_id' => $member->id]);

    $member->forceDelete();

    expect(Membership::query()->count())->toBe(0);
});

it('creates member relationships', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    $relationship = MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Employee',
    ]);

    expect($relationship->member->id)->toBe($contact->id)
        ->and($relationship->relatedMember->id)->toBe($org->id)
        ->and($relationship->relationship_type)->toBe('Employee');
});

it('enforces unique member relationship pairs', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    expect(fn () => MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('navigates organisations via belongs to many', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
        'relationship_type' => 'Employee',
    ]);

    expect($contact->organisations)->toHaveCount(1)
        ->and($contact->organisations->first()->id)->toBe($org->id);
});

it('navigates contacts via inverse belongs to many', function () {
    $contact = Member::factory()->contact()->create();
    $org = Member::factory()->organisation()->create();

    MemberRelationship::factory()->create([
        'member_id' => $contact->id,
        'related_member_id' => $org->id,
    ]);

    expect($org->contacts)->toHaveCount(1)
        ->and($org->contacts->first()->id)->toBe($contact->id);
});

it('relates member to organisation tax class', function () {
    $taxClass = OrganisationTaxClass::factory()->create();
    $member = Member::factory()->organisation()->create([
        'organisation_tax_class_id' => $taxClass->id,
    ]);

    expect($member->organisationTaxClass->id)->toBe($taxClass->id);
});

it('relates user to member via belongs to', function () {
    $member = Member::factory()->user()->create();
    $user = User::factory()->create(['member_id' => $member->id]);

    expect($user->member->id)->toBe($member->id)
        ->and($member->user->id)->toBe($user->id);
});

it('nullifies user member_id when member is force deleted', function () {
    $member = Member::factory()->user()->create();
    $user = User::factory()->create(['member_id' => $member->id]);

    $member->forceDelete();
    $user->refresh();

    expect($user->member_id)->toBeNull();
});

it('casts tag_list as array', function () {
    $member = Member::factory()->create(['tag_list' => ['vip', 'preferred']]);
    $member->refresh();

    expect($member->tag_list)->toBe(['vip', 'preferred']);
});

it('relates store to country', function () {
    $country = Country::factory()->create();
    $store = Store::factory()->create(['country_id' => $country->id]);

    expect($store->country->id)->toBe($country->id);
});
