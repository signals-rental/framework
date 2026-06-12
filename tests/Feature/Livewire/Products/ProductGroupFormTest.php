<?php

use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('shows the group image upload section in edit mode', function () {
    $group = ProductGroup::factory()->create(['name' => 'Editable Group']);

    Volt::test('product-groups.form', ['productGroup' => $group])
        ->assertSee('Group Image');
});

it('shows the group image upload section in create mode', function () {
    Volt::test('product-groups.form')
        ->assertSee('Group Image');
});

it('creates a group with an uploaded image', function () {
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);

    $photo = UploadedFile::fake()->image('group.jpg', 300, 300);

    Volt::test('product-groups.form')
        ->set('name', 'Lighting With Icon')
        ->set('photo', $photo)
        ->call('save');

    $group = ProductGroup::where('name', 'Lighting With Icon')->firstOrFail();

    expect($group->icon_url)->not->toBeNull()
        ->and($group->icon_thumb_url)->not->toBeNull();
});

it('creates a group without an image when none is uploaded', function () {
    Volt::test('product-groups.form')
        ->set('name', 'No Icon Group')
        ->call('save');

    $group = ProductGroup::where('name', 'No Icon Group')->firstOrFail();

    expect($group->icon_url)->toBeNull()
        ->and($group->icon_thumb_url)->toBeNull();
});
