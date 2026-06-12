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

it('creates a group with a parent group', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Lighting']);

    Volt::test('product-groups.form')
        ->set('name', 'Moving Heads')
        ->set('parentId', $parent->id)
        ->call('save');

    $group = ProductGroup::where('name', 'Moving Heads')->firstOrFail();

    expect($group->parent_id)->toBe($parent->id);
});

it('hydrates the parent group when editing', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Sound']);
    $group = ProductGroup::factory()->create(['name' => 'Speakers', 'parent_id' => $parent->id]);

    Volt::test('product-groups.form', ['productGroup' => $group])
        ->assertSet('parentId', $parent->id);
});

it('changes the parent group when editing', function () {
    $oldParent = ProductGroup::factory()->create(['name' => 'Old Parent']);
    $newParent = ProductGroup::factory()->create(['name' => 'New Parent']);
    $group = ProductGroup::factory()->create(['name' => 'Child', 'parent_id' => $oldParent->id]);

    Volt::test('product-groups.form', ['productGroup' => $group])
        ->set('parentId', $newParent->id)
        ->call('save');

    expect($group->fresh()->parent_id)->toBe($newParent->id);
});

it('clears the parent group when None is selected on edit', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Parent']);
    $group = ProductGroup::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

    Volt::test('product-groups.form', ['productGroup' => $group])
        ->set('parentId', null)
        ->call('save');

    expect($group->fresh()->parent_id)->toBeNull();
});

it('preserves name and description when only clearing the parent on edit', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Parent']);
    $group = ProductGroup::factory()->create([
        'name' => 'Keep Me',
        'description' => 'Keep this description',
        'parent_id' => $parent->id,
    ]);

    Volt::test('product-groups.form', ['productGroup' => $group])
        ->set('parentId', null)
        ->call('save');

    $group->refresh();

    expect($group->parent_id)->toBeNull()
        ->and($group->name)->toBe('Keep Me')
        ->and($group->description)->toBe('Keep this description');
});

it('excludes the current group from the parent options when editing', function () {
    $other = ProductGroup::factory()->create(['name' => 'Other Group']);
    $group = ProductGroup::factory()->create(['name' => 'Self Group']);

    $component = Volt::test('product-groups.form', ['productGroup' => $group]);

    $parentOptions = $component->viewData('parentOptions');

    expect($parentOptions->pluck('id'))->not->toContain($group->id)
        ->and($parentOptions->pluck('id'))->toContain($other->id);
});
