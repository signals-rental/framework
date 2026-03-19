<?php

use App\Data\Members\MergeMemberData;
use App\Models\Member;
use Illuminate\Validation\ValidationException;

it('valid data passes validation', function () {
    $memberA = Member::factory()->create();
    $memberB = Member::factory()->create();

    $data = MergeMemberData::validateAndCreate([
        'primary_id' => $memberA->id,
        'secondary_id' => $memberB->id,
    ]);

    expect($data)->toBeInstanceOf(MergeMemberData::class)
        ->and($data->primary_id)->toBe($memberA->id)
        ->and($data->secondary_id)->toBe($memberB->id);
});

it('rejects same ID for primary and secondary', function () {
    $member = Member::factory()->create();

    MergeMemberData::validateAndCreate([
        'primary_id' => $member->id,
        'secondary_id' => $member->id,
    ]);
})->throws(ValidationException::class);

it('rejects nonexistent member IDs for primary_id', function () {
    $member = Member::factory()->create();

    MergeMemberData::validateAndCreate([
        'primary_id' => 999999,
        'secondary_id' => $member->id,
    ]);
})->throws(ValidationException::class);

it('rejects nonexistent member IDs for secondary_id', function () {
    $member = Member::factory()->create();

    MergeMemberData::validateAndCreate([
        'primary_id' => $member->id,
        'secondary_id' => 999999,
    ]);
})->throws(ValidationException::class);

it('rejects missing primary_id', function () {
    $member = Member::factory()->create();

    MergeMemberData::validateAndCreate([
        'secondary_id' => $member->id,
    ]);
})->throws(ValidationException::class);

it('rejects missing secondary_id', function () {
    $member = Member::factory()->create();

    MergeMemberData::validateAndCreate([
        'primary_id' => $member->id,
    ]);
})->throws(ValidationException::class);
