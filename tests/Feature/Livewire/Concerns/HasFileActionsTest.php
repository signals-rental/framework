<?php

use App\Models\Attachment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the product files page', function () {
    $product = Product::factory()->create();

    $this->get(route('products.files', $product))
        ->assertOk();
});

it('deletes an attachment when authorized', function () {
    $product = Product::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Product::class,
        'attachable_id' => $product->id,
        'uploaded_by' => $this->user->id,
    ]);

    Volt::test('products.files', ['product' => $product])
        ->call('confirmDelete', $attachment->id)
        ->assertSet('deleteAttachmentId', $attachment->id)
        ->call('deleteAttachment')
        ->assertSet('deleteAttachmentId', null);

    expect(Attachment::find($attachment->id))->toBeNull();
});

it('rejects deletion when user lacks authorization', function () {
    $unprivilegedUser = User::factory()->create();
    $this->actingAs($unprivilegedUser);

    $product = Product::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Product::class,
        'attachable_id' => $product->id,
        'uploaded_by' => $this->user->id, // uploaded by a different user
    ]);

    Volt::test('products.files', ['product' => $product])
        ->call('confirmDelete', $attachment->id)
        ->call('deleteAttachment')
        ->assertForbidden();
});

it('cancels delete and resets deleteAttachmentId', function () {
    $product = Product::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Product::class,
        'attachable_id' => $product->id,
    ]);

    Volt::test('products.files', ['product' => $product])
        ->call('confirmDelete', $attachment->id)
        ->assertSet('deleteAttachmentId', $attachment->id)
        ->call('cancelDelete')
        ->assertSet('deleteAttachmentId', null);

    // Attachment should still exist
    expect(Attachment::find($attachment->id))->not->toBeNull();
});
