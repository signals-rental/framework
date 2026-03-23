<?php

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;

it('belongs to an activity', function () {
    $participant = ActivityParticipant::factory()->create();

    expect($participant->activity)->toBeInstanceOf(Activity::class);
});

it('belongs to a member', function () {
    $participant = ActivityParticipant::factory()->create();

    expect($participant->member)->toBeInstanceOf(Member::class);
});

it('casts mute to boolean', function () {
    $participant = ActivityParticipant::factory()->create(['mute' => true]);

    expect($participant->mute)->toBeTrue()->toBeBool();
});

it('defaults mute to false', function () {
    $participant = ActivityParticipant::factory()->create(['mute' => false]);

    expect($participant->mute)->toBeFalse();
});
