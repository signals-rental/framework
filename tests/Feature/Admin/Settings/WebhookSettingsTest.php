<?php

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('renders the webhooks settings page', function () {
    $this->get(route('admin.settings.webhooks'))
        ->assertOk()
        ->assertSee('Webhooks');
});

it('shows empty state when no webhooks exist', function () {
    $this->get(route('admin.settings.webhooks'))
        ->assertSee('No webhooks');
});

it('lists existing webhooks', function () {
    Webhook::factory()->count(3)->create(['user_id' => $this->owner->id]);

    $this->get(route('admin.settings.webhooks'))
        ->assertOk();

    $webhooks = Webhook::where('user_id', $this->owner->id)->get();
    expect($webhooks)->toHaveCount(3);
});

it('can create a webhook', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['user.created', 'user.updated'])
        ->call('createWebhook')
        ->assertHasNoErrors();

    expect(Webhook::count())->toBe(1);
    expect(Webhook::first()->url)->toBe('https://example.com/webhook');
    expect(Webhook::first()->events)->toBe(['user.created', 'user.updated']);
});

it('shows the secret after creation', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook')
        ->assertSet('showSecret', true)
        ->assertNotSet('plainSecret', '');
});

it('dispatches event on webhook creation', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook')
        ->assertDispatched('webhook-created');
});

it('validates webhook creation requires url', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', '')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook')
        ->assertHasErrors(['createUrl']);
});

it('validates webhook creation requires events', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', [])
        ->call('createWebhook')
        ->assertHasErrors(['createEvents']);
});

it('validates webhook creation requires valid url', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'not-a-url')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook')
        ->assertHasErrors(['createUrl']);
});

it('validates webhook creation rejects invalid events', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['invalid.event'])
        ->call('createWebhook')
        ->assertHasErrors(['createEvents.0']);
});

it('can edit a webhook', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);

    Volt::test('admin.settings.webhooks')
        ->call('openEditModal', $webhook->id)
        ->set('editUrl', 'https://new-url.com/hook')
        ->set('editEvents', ['role.created', 'role.deleted'])
        ->call('updateWebhook')
        ->assertHasNoErrors();

    expect($webhook->fresh()->url)->toBe('https://new-url.com/hook');
    expect($webhook->fresh()->events)->toBe(['role.created', 'role.deleted']);
});

it('dispatches event on webhook update', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);

    Volt::test('admin.settings.webhooks')
        ->call('openEditModal', $webhook->id)
        ->set('editUrl', 'https://new-url.com/hook')
        ->call('updateWebhook')
        ->assertDispatched('webhook-updated');
});

it('populates edit modal with current values', function () {
    $webhook = Webhook::factory()->create([
        'user_id' => $this->owner->id,
        'url' => 'https://original.com/hook',
        'events' => ['user.created', 'settings.updated'],
        'is_active' => true,
    ]);

    Volt::test('admin.settings.webhooks')
        ->call('openEditModal', $webhook->id)
        ->assertSet('editUrl', 'https://original.com/hook')
        ->assertSet('editEvents', ['user.created', 'settings.updated'])
        ->assertSet('editIsActive', true);
});

it('can delete a webhook', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);

    Volt::test('admin.settings.webhooks')
        ->call('confirmDelete', $webhook->id)
        ->call('deleteWebhook')
        ->assertHasNoErrors();

    expect(Webhook::count())->toBe(0);
});

it('dispatches event on webhook deletion', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);

    Volt::test('admin.settings.webhooks')
        ->call('confirmDelete', $webhook->id)
        ->call('deleteWebhook')
        ->assertDispatched('webhook-deleted');
});

it('can re-enable a disabled webhook', function () {
    $webhook = Webhook::factory()->disabled()->create([
        'user_id' => $this->owner->id,
        'consecutive_failures' => 18,
    ]);

    Volt::test('admin.settings.webhooks')
        ->call('reenableWebhook', $webhook->id)
        ->assertHasNoErrors();

    expect($webhook->fresh()->is_active)->toBeTrue();
    expect($webhook->fresh()->consecutive_failures)->toBe(0);
    expect($webhook->fresh()->disabled_at)->toBeNull();
});

it('dispatches event on webhook re-enable', function () {
    $webhook = Webhook::factory()->disabled()->create([
        'user_id' => $this->owner->id,
        'consecutive_failures' => 18,
    ]);

    Volt::test('admin.settings.webhooks')
        ->call('reenableWebhook', $webhook->id)
        ->assertDispatched('webhook-reenabled');
});

it('can view delivery logs', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);
    WebhookLog::factory()->count(5)->create(['webhook_id' => $webhook->id]);

    Volt::test('admin.settings.webhooks')
        ->call('viewLogs', $webhook->id)
        ->assertSet('showLogsModal', true)
        ->assertSet('viewingLogsWebhookId', $webhook->id);
});

it('can close the logs modal', function () {
    $webhook = Webhook::factory()->create(['user_id' => $this->owner->id]);

    Volt::test('admin.settings.webhooks')
        ->call('viewLogs', $webhook->id)
        ->assertSet('showLogsModal', true)
        ->call('closeLogsModal')
        ->assertSet('showLogsModal', false)
        ->assertSet('viewingLogsWebhookId', null);
});

it('can dismiss secret display', function () {
    $component = Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook');

    $component->assertSet('showSecret', true);

    $component->call('dismissSecret')
        ->assertSet('showSecret', false)
        ->assertSet('plainSecret', '');
});

it('assigns webhook to current user on creation', function () {
    Volt::test('admin.settings.webhooks')
        ->set('createUrl', 'https://example.com/webhook')
        ->set('createEvents', ['user.created'])
        ->call('createWebhook');

    expect(Webhook::first()->user_id)->toBe($this->owner->id);
});

it('requires admin access', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('admin.settings.webhooks'))
        ->assertForbidden();
});
