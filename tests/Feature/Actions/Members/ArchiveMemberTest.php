<?php

use App\Actions\Members\ArchiveMember;
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

it('archives a member by setting is_active to false and soft-deleting', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->organisation()->create(['is_active' => true]);

    (new ArchiveMember)($member);

    expect(Member::find($member->id))->toBeNull();

    $trashed = Member::withTrashed()->find($member->id);
    expect($trashed)->not->toBeNull()
        ->and($trashed->is_active)->toBeFalse();
});

it('fires AuditableEvent with member.archived action', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->create();

    (new ArchiveMember)($member);

    Event::assertDispatched(AuditableEvent::class, function (AuditableEvent $event) {
        return $event->action === 'member.archived';
    });
});

it('dispatches webhook with member.archived event', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['member.archived'],
        'is_active' => true,
    ]);

    $member = Member::factory()->create();

    (new ArchiveMember)($member);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'member.archived';
    });
});

it('denies unauthorized users without members.delete permission', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $member = Member::factory()->create();

    (new ArchiveMember)($member);
})->throws(AuthorizationException::class);

it('makes archived member invisible to normal queries but findable via withTrashed', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    $member = Member::factory()->contact()->create(['name' => 'Archived Contact']);

    (new ArchiveMember)($member);

    expect(Member::where('name', 'Archived Contact')->exists())->toBeFalse();
    expect(Member::withTrashed()->where('name', 'Archived Contact')->exists())->toBeTrue();
});
