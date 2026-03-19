<?php

use App\Actions\Members\RestoreMember;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Member;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('restores a soft-deleted member and sets is_active to true', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->create(['is_active' => false]);
    $member->delete();

    expect(Member::find($member->id))->toBeNull();

    (new RestoreMember)($member);

    $restored = Member::find($member->id);
    expect($restored)->not->toBeNull()
        ->and($restored->is_active)->toBeTrue()
        ->and($restored->deleted_at)->toBeNull();
});

it('fires AuditableEvent with member.restored action', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->create(['is_active' => false]);
    $member->delete();

    (new RestoreMember)($member);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'member.restored';
    });
});

it('dispatches webhook with member.restored event', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['member.restored'],
        'is_active' => true,
    ]);

    $member = Member::factory()->create(['is_active' => false]);
    $member->delete();

    (new RestoreMember)($member);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'member.restored';
    });
});

it('does nothing when restoring a non-deleted member', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->create(['is_active' => true]);

    (new RestoreMember)($member);

    // Should remain unchanged — no events fired
    Event::assertNotDispatched(AuditableEvent::class);
    expect(Member::find($member->id)->is_active)->toBeTrue();
});

it('denies unauthorized users without members.delete permission', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $member = Member::factory()->create(['is_active' => false]);
    $member->delete();

    (new RestoreMember)($member);
})->throws(AuthorizationException::class);
