<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
});

it('renders the branding page', function () {
    $this->get(route('admin.settings.branding'))
        ->assertOk()
        ->assertSee('Branding');
});

it('loads current branding values', function () {
    settings()->setMany([
        'branding.primary_colour' => '#ff0000',
        'branding.accent_colour' => '#00ff00',
    ]);

    Volt::test('admin.settings.branding')
        ->assertSet('primaryColour', '#ff0000')
        ->assertSet('accentColour', '#00ff00');
});

it('saves branding colours', function () {
    Volt::test('admin.settings.branding')
        ->set('primaryColour', '#223344')
        ->set('accentColour', '#556677')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('branding-settings-saved');

    expect(settings('branding.primary_colour'))->toBe('#223344');
    expect(settings('branding.accent_colour'))->toBe('#556677');
});

it('validates hex colour format', function () {
    Volt::test('admin.settings.branding')
        ->set('primaryColour', 'not-a-colour')
        ->set('accentColour', '#GGG')
        ->call('save')
        ->assertHasErrors(['primaryColour', 'accentColour']);
});

it('uploads a logo', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    Volt::test('admin.settings.branding')
        ->set('primaryColour', '#1e3a5f')
        ->set('accentColour', '#3b82f6')
        ->set('logo', $file)
        ->call('save')
        ->assertHasNoErrors();

    expect(settings('branding.logo_path'))->not->toBeNull();
    Storage::disk('public')->assertExists(settings('branding.logo_path'));
});

it('validates logo file type', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 100);

    Volt::test('admin.settings.branding')
        ->set('primaryColour', '#1e3a5f')
        ->set('accentColour', '#3b82f6')
        ->set('logo', $file)
        ->call('save')
        ->assertHasErrors(['logo']);
});

it('validates logo file size', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('huge-logo.png')->size(3000);

    Volt::test('admin.settings.branding')
        ->set('primaryColour', '#1e3a5f')
        ->set('accentColour', '#3b82f6')
        ->set('logo', $file)
        ->call('save')
        ->assertHasErrors(['logo']);
});

it('can remove a logo', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    Volt::test('admin.settings.branding')
        ->set('primaryColour', '#1e3a5f')
        ->set('accentColour', '#3b82f6')
        ->set('logo', $file)
        ->call('save');

    $logoPath = settings('branding.logo_path');
    Storage::disk('public')->assertExists($logoPath);

    Volt::test('admin.settings.branding')
        ->call('removeLogo')
        ->assertSet('currentLogoPath', null);

    expect(settings('branding.logo_path'))->toBeNull();
    Storage::disk('public')->assertMissing($logoPath);
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.branding'))
        ->assertForbidden();
});
