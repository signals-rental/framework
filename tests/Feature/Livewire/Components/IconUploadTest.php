<?php

use App\Livewire\Components\IconUpload;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create();
});

it('renders with no existing icon', function () {
    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->assertSee('Upload')
        ->assertDontSee('Remove');
});

it('uploads an icon image', function () {
    $photo = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->set('photo', $photo)
        ->assertDispatched('icon-updated');

    $this->member->refresh();
    expect($this->member->icon_url)->not->toBeNull();
    expect($this->member->icon_thumb_url)->not->toBeNull();
});

it('removes an existing icon', function () {
    $this->member->update([
        'icon_url' => 'icons/test.jpg',
        'icon_thumb_url' => 'icons/thumbs/test.jpg',
    ]);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->assertSee('Remove')
        ->call('removeIcon')
        ->assertDispatched('icon-updated');

    $this->member->refresh();
    expect($this->member->icon_url)->toBeNull();
    expect($this->member->icon_thumb_url)->toBeNull();
});

it('validates file type', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->set('photo', $file)
        ->assertHasErrors(['photo']);
});

it('validates file size', function () {
    $file = UploadedFile::fake()->image('huge.jpg')->size(3000);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->set('photo', $file)
        ->assertHasErrors(['photo']);
});

it('shows replace button when icon exists', function () {
    $this->member->update([
        'icon_url' => 'icons/test.jpg',
        'icon_thumb_url' => 'icons/thumbs/test.jpg',
    ]);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->assertSee('Replace')
        ->assertSee('Remove');
});

it('denies upload when user lacks update permission', function () {
    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged);

    $photo = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->set('photo', $photo)
        ->assertForbidden();
});

it('denies icon removal when user lacks update permission', function () {
    $this->member->update([
        'icon_url' => 'icons/test.jpg',
        'icon_thumb_url' => 'icons/thumbs/test.jpg',
    ]);

    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->call('removeIcon')
        ->assertForbidden();
});

it('returns null thumb display url when no thumb path', function () {
    /** @var IconUpload $instance */
    $instance = Livewire::test(IconUpload::class, ['model' => $this->member])->instance();

    expect($instance->getThumbDisplayUrlProperty())->toBeNull();
});

it('returns null thumb display url when file service throws', function () {
    $this->member->update([
        'icon_url' => 'icons/test.jpg',
        'icon_thumb_url' => 'icons/thumbs/test.jpg',
    ]);

    $mockService = Mockery::mock(\App\Services\FileService::class);
    $mockService->shouldReceive('signedUrl')->andThrow(new \RuntimeException('S3 unavailable'));
    app()->instance(\App\Services\FileService::class, $mockService);

    /** @var IconUpload $instance */
    $instance = Livewire::test(IconUpload::class, ['model' => $this->member])->instance();

    expect($instance->getThumbDisplayUrlProperty())->toBeNull();
});

it('handles upload failure gracefully', function () {
    $mockService = Mockery::mock(\App\Services\FileService::class);
    $mockService->shouldReceive('uploadIcon')->andThrow(new \RuntimeException('Upload failed'));
    app()->instance(\App\Services\FileService::class, $mockService);

    $photo = UploadedFile::fake()->image('avatar.jpg', 300, 300);

    Livewire::test(IconUpload::class, ['model' => $this->member])
        ->set('photo', $photo)
        ->assertHasErrors(['photo']);
});

it('mounts with existing icon paths from model', function () {
    $this->member->update([
        'icon_url' => 'icons/existing.jpg',
        'icon_thumb_url' => 'icons/thumbs/existing.jpg',
    ]);

    $component = Livewire::test(IconUpload::class, ['model' => $this->member]);

    expect($component->get('iconPath'))->toBe('icons/existing.jpg')
        ->and($component->get('thumbPath'))->toBe('icons/thumbs/existing.jpg');
});
