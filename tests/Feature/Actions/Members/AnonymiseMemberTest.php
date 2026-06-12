<?php

use App\Actions\Members\AnonymiseMember;
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
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('anonymises a member record', function () {
    $member = Member::factory()->create(['name' => 'John Doe', 'description' => 'Important client']);

    $result = (new AnonymiseMember)($member);

    expect($result->name)->toBe('Anonymised Member');
    expect($result->description)->toBeNull();
});

it('deletes related contact details', function () {
    $member = Member::factory()->create();
    $member->emails()->create([
        'address' => 'test@example.com',
        'is_primary' => true,
    ]);
    $member->phones()->create([
        'number' => '01onal234567',
        'is_primary' => true,
    ]);

    (new AnonymiseMember)($member);

    expect($member->emails()->count())->toBe(0);
    expect($member->phones()->count())->toBe(0);
});

it('prevents self-anonymisation', function () {
    $member = Member::factory()->create();
    $user = User::factory()->owner()->create(['member_id' => $member->id]);
    $this->actingAs($user);

    (new AnonymiseMember)($member);
})->throws(ValidationException::class);

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $member = Member::factory()->create();

    (new AnonymiseMember)($member);
})->throws(AuthorizationException::class);

it('fires an auditable event', function () {
    Event::fake();

    $member = Member::factory()->create(['name' => 'Jane Doe']);

    (new AnonymiseMember)($member);

    Event::assertDispatched(AuditableEvent::class, function ($event) {
        return $event->action === 'member.anonymised';
    });
});

it('dispatches a member.anonymised webhook to subscribers', function () {
    Event::fake([AuditableEvent::class]);
    Queue::fake([DeliverWebhook::class]);

    Webhook::factory()->create([
        'events' => ['member.anonymised'],
        'is_active' => true,
    ]);

    $member = Member::factory()->create();

    (new AnonymiseMember)($member);

    Queue::assertPushed(
        DeliverWebhook::class,
        fn (DeliverWebhook $job): bool => $job->event === 'member.anonymised'
            && $job->payload['id'] === $member->id,
    );
});
