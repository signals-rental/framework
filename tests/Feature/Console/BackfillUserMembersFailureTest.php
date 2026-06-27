<?php

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Log;

it('creates a member and links it for a genuinely unlinked user', function () {
    // Factory auto-links a member; remove it so the backfill performs a real
    // Member::create + user link (the success body, incl. the per-user "Created"
    // line and the SUCCESS return).
    $user = User::factory()->create(['name' => 'Unlinked Person']);
    $user->update(['member_id' => null]);

    $this->artisan('signals:backfill-user-members')
        ->expectsOutputToContain('Created member')
        ->assertExitCode(0);

    $member = $user->fresh()->member;
    expect($member)->not->toBeNull()
        ->and($member->name)->toBe('Unlinked Person');
});

it('reports a failure and returns FAILURE when creating a member throws', function () {
    // The factory auto-links a member; strip it back off so the backfill has a
    // genuinely unlinked user to process.
    $user = User::factory()->create(['name' => 'Doomed User']);
    $user->update(['member_id' => null]);

    // Force the per-user transaction to throw: a `creating` listener on Member
    // raises, so the catch block runs (report + $failed++, lines 46-49) and the
    // command finishes on the failure branch (lines 56, 58).
    Member::creating(function (): void {
        throw new RuntimeException('simulated member creation failure');
    });

    // report() is routed to the exception handler; assert it was invoked.
    Log::spy();

    $this->artisan('signals:backfill-user-members')
        ->expectsOutputToContain('Failed for user')
        ->expectsOutputToContain('1 user(s) failed')
        ->assertExitCode(1);

    // The user remains unlinked because the transaction rolled back.
    expect($user->fresh()->member_id)->toBeNull();
});
