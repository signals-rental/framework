<?php

use App\Actions\Admin\CreateRole;
use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\InviteUser;
use App\Actions\Admin\UpdateRole;
use App\Actions\Admin\UpdateUser;
use App\Data\Admin\InviteUserData;
use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);

    // Create an active webhook subscribed to all events
    Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'events' => [
            'user.created', 'user.updated', 'user.deactivated',
            'role.created', 'role.updated', 'role.deleted',
            'settings.updated',
        ],
        'is_active' => true,
    ]);
});

it('dispatches user.created webhook when inviting a user', function () {
    Queue::fake();
    Notification::fake();

    (new InviteUser)(InviteUserData::from([
        'name' => 'Test User',
        'email' => 'webhook-test@example.com',
        'roles' => [],
    ]));

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'user.created';
    });
});

it('dispatches user.updated webhook when updating a user', function () {
    Queue::fake();
    $user = User::factory()->create();

    (new UpdateUser)($user, ['name' => 'Updated Name']);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'user.updated';
    });
});

it('dispatches user.deactivated webhook when deactivating a user', function () {
    Queue::fake();
    $user = User::factory()->create();

    (new DeactivateUser)($user);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'user.deactivated';
    });
});

it('dispatches role.created webhook when creating a role', function () {
    Queue::fake();

    (new CreateRole)(['name' => 'Webhook Test Role', 'permissions' => []]);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'role.created';
    });
});

it('dispatches role.updated webhook when updating a role', function () {
    Queue::fake();
    /** @var Role $role */
    $role = Role::create(['name' => 'Editable', 'guard_name' => 'web', 'is_system' => false]);

    (new UpdateRole)($role, ['name' => 'Updated']);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'role.updated';
    });
});

it('dispatches role.deleted webhook when deleting a role', function () {
    Queue::fake();
    /** @var Role $role */
    $role = Role::create(['name' => 'Deletable', 'guard_name' => 'web', 'is_system' => false]);

    (new DeleteRole)($role);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) {
        return $job->event === 'role.deleted';
    });
});

it('does not dispatch webhooks when no active webhooks exist', function () {
    Queue::fake();
    Webhook::query()->delete();

    (new CreateRole)(['name' => 'No Webhook Role', 'permissions' => []]);

    Queue::assertNotPushed(DeliverWebhook::class);
});

it('includes correct payload in user webhook', function () {
    Queue::fake();
    Notification::fake();

    $user = (new InviteUser)(InviteUserData::from([
        'name' => 'Payload User',
        'email' => 'payload@example.com',
        'roles' => [],
    ]));

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) use ($user) {
        return $job->event === 'user.created'
            && $job->payload['user']['id'] === $user->id
            && $job->payload['user']['name'] === 'Payload User'
            && $job->payload['user']['email'] === 'payload@example.com';
    });
});

it('includes correct payload in role webhook', function () {
    Queue::fake();

    $role = (new CreateRole)(['name' => 'Payload Role', 'permissions' => []]);

    Queue::assertPushed(DeliverWebhook::class, function (DeliverWebhook $job) use ($role) {
        return $job->event === 'role.created'
            && $job->payload['role']['id'] === $role->id
            && $job->payload['role']['name'] === 'Payload Role';
    });
});
