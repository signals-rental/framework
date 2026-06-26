<?php

use App\Models\Attachment;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use App\Services\FileService;
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

    $flashed = captureFlashedMessages(function () use ($product, $attachment): void {
        Volt::test('products.files', ['product' => $product])
            ->call('confirmDelete', $attachment->id)
            ->call('deleteAttachment');
    });

    expect($flashed['error'] ?? null)->toBe('You do not have permission to delete this file.')
        ->and(Attachment::find($attachment->id))->not->toBeNull();
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

it('returns early from deleteAttachment when no attachment id is set', function () {
    $member = Member::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Member::class,
        'attachable_id' => $member->id,
    ]);

    Volt::test('members.files', ['member' => $member])
        ->call('deleteAttachment')
        ->assertSet('deleteAttachmentId', null);

    expect(Attachment::find($attachment->id))->not->toBeNull();
});

it('flashes info when deleting an attachment that was already removed', function () {
    $member = Member::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Member::class,
        'attachable_id' => $member->id,
    ]);

    $component = Volt::test('members.files', ['member' => $member])
        ->call('confirmDelete', $attachment->id);

    $attachment->delete();

    $flashed = captureFlashedMessages(function () use ($component): void {
        $component->call('deleteAttachment');
    });

    expect($flashed['info'] ?? null)->toBe('File was already deleted.')
        ->and($component->get('deleteAttachmentId'))->toBeNull();
});

it('flashes error when file deletion fails unexpectedly', function () {
    $this->mock(FileService::class, function ($mock): void {
        $mock->shouldReceive('delete')->andThrow(new RuntimeException('Storage unavailable'));
    });

    $member = Member::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => Member::class,
        'attachable_id' => $member->id,
        'uploaded_by' => $this->user->id,
    ]);

    $flashed = captureFlashedMessages(function () use ($member, $attachment): void {
        Volt::test('members.files', ['member' => $member])
            ->call('confirmDelete', $attachment->id)
            ->call('deleteAttachment');
    });

    expect($flashed['error'] ?? null)->toBe('The file could not be deleted. Please try again.')
        ->and(Attachment::find($attachment->id))->not->toBeNull();
});

it('refreshes attachment counts after a file upload event', function () {
    $member = Member::factory()->create();

    $component = Volt::test('members.files', ['member' => $member]);

    Attachment::factory()->create([
        'attachable_type' => Member::class,
        'attachable_id' => $member->id,
    ]);

    $component->call('refreshFiles');

    expect($component->get('member')->attachments_count)->toBe(1);
});
