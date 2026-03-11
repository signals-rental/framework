<?php

use App\Models\Address;
use App\Models\Country;
use App\Models\Email;
use App\Models\Link;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\Phone;

it('creates an address for a member', function () {
    $member = Member::factory()->create();
    $address = Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $member->id,
        'city' => 'London',
    ]);

    expect($address->addressable)->toBeInstanceOf(Member::class)
        ->and($address->addressable_id)->toBe($member->id)
        ->and($address->city)->toBe('London');
});

it('creates a primary address', function () {
    $address = Address::factory()->primary()->create();

    expect($address->is_primary)->toBeTrue();
});

it('relates address to country', function () {
    $country = Country::factory()->create();
    $address = Address::factory()->create(['country_id' => $country->id]);

    expect($address->country->id)->toBe($country->id);
});

it('relates address to list value type', function () {
    $type = ListValue::factory()->create();
    $address = Address::factory()->create(['type_id' => $type->id]);

    expect($address->type->id)->toBe($type->id);
});

it('creates an email for a member', function () {
    $member = Member::factory()->create();
    $email = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $member->id,
        'address' => 'test@example.com',
    ]);

    expect($email->emailable)->toBeInstanceOf(Member::class)
        ->and($email->emailable_id)->toBe($member->id)
        ->and($email->address)->toBe('test@example.com');
});

it('creates a primary email', function () {
    $email = Email::factory()->primary()->create();

    expect($email->is_primary)->toBeTrue();
});

it('creates a phone for a member', function () {
    $member = Member::factory()->create();
    $phone = Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $member->id,
        'number' => '+44 1234 567890',
    ]);

    expect($phone->phoneable)->toBeInstanceOf(Member::class)
        ->and($phone->phoneable_id)->toBe($member->id)
        ->and($phone->number)->toBe('+44 1234 567890');
});

it('creates a primary phone', function () {
    $phone = Phone::factory()->primary()->create();

    expect($phone->is_primary)->toBeTrue();
});

it('creates a link for a member', function () {
    $member = Member::factory()->create();
    $link = Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $member->id,
        'url' => 'https://example.com',
        'name' => 'Website',
    ]);

    expect($link->linkable)->toBeInstanceOf(Member::class)
        ->and($link->linkable_id)->toBe($member->id)
        ->and($link->url)->toBe('https://example.com')
        ->and($link->name)->toBe('Website');
});

it('accesses member contact details via morph many', function () {
    $member = Member::factory()->create();

    Address::factory()->create([
        'addressable_type' => Member::class,
        'addressable_id' => $member->id,
    ]);
    Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $member->id,
    ]);
    Phone::factory()->create([
        'phoneable_type' => Member::class,
        'phoneable_id' => $member->id,
    ]);
    Link::factory()->create([
        'linkable_type' => Member::class,
        'linkable_id' => $member->id,
    ]);

    $member->refresh();

    expect($member->addresses)->toHaveCount(1)
        ->and($member->emails)->toHaveCount(1)
        ->and($member->phones)->toHaveCount(1)
        ->and($member->links)->toHaveCount(1);
});

it('eager loads member with all contact details', function () {
    $member = Member::factory()->create();
    Address::factory()->count(2)->create([
        'addressable_type' => Member::class,
        'addressable_id' => $member->id,
    ]);
    Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $member->id,
    ]);

    $loaded = Member::query()
        ->with(['addresses', 'emails', 'phones', 'links'])
        ->find($member->id);

    expect($loaded->addresses)->toHaveCount(2)
        ->and($loaded->emails)->toHaveCount(1)
        ->and($loaded->phones)->toHaveCount(0)
        ->and($loaded->links)->toHaveCount(0);
});
