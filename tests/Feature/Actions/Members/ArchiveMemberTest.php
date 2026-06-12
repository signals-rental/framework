<?php

use App\Actions\Members\ArchiveMember;
use App\Events\AuditableEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Member;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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

/**
 * Regression guard for in-transaction webhook dispatch (todos #137 + #140).
 *
 * ArchiveMember dispatches its webhook from inside a DB::transaction. The job
 * declares `public bool $afterCommit = true`, so when the surrounding
 * transaction rolls back the DeliverWebhook job is discarded by the
 * DatabaseTransactionsManager and never runs — no phantom delivery for a
 * mutation that never persisted.
 *
 * This test deliberately does NOT use Queue::fake(): QueueFake::push() records
 * jobs synchronously and ignores afterCommit/transaction state entirely, so it
 * cannot exercise the after-commit semantics. Instead it relies on the real
 * `sync` queue (the test default), which routes dispatch through
 * Queue::enqueueUsing() and honours the afterCommit flag. We assert the
 * effect: the DeliverWebhook job, which writes a WebhookLog row and sends an
 * HTTP request when it runs, does neither after the wrapping transaction is
 * rolled back. Http::fake() prevents any real outbound request if the guard
 * were to regress.
 */
it('does not deliver the webhook when the surrounding transaction rolls back', function () {
    Event::fake([AuditableEvent::class]);
    Http::fake();

    Webhook::factory()->create([
        'events' => ['member.archived'],
        'is_active' => true,
    ]);

    $member = Member::factory()->create();

    $thrown = false;

    try {
        DB::transaction(function () use ($member): void {
            (new ArchiveMember)($member);

            throw new RuntimeException('forced rollback after archive');
        });
    } catch (RuntimeException $e) {
        $thrown = $e->getMessage() === 'forced rollback after archive';
    }

    expect($thrown)->toBeTrue();

    // After-commit job was discarded on rollback: no log, no HTTP delivery.
    expect(WebhookLog::query()->where('event', 'member.archived')->exists())->toBeFalse();
    Http::assertNothingSent();
});

/**
 * Positive counterpart: with no rollback, the after-commit job runs as normal
 * once ArchiveMember's transaction commits, producing a real delivery attempt.
 */
it('delivers the webhook after the transaction commits', function () {
    Event::fake([AuditableEvent::class]);
    Http::fake(['*' => Http::response('', 200)]);

    Webhook::factory()->create([
        'events' => ['member.archived'],
        'is_active' => true,
    ]);

    $member = Member::factory()->create();

    (new ArchiveMember)($member);

    expect(WebhookLog::query()->where('event', 'member.archived')->exists())->toBeTrue();
    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Signals-Event', 'member.archived'));
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
