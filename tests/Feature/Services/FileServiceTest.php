<?php

use App\Models\Attachment;
use App\Models\Member;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // FileService maps 'local' → 'public' for web-accessible storage
    $default = config('filesystems.default', 'local');
    $this->disk = $default === 'local' ? 'public' : $default;
    Storage::fake($this->disk);
    $this->service = new FileService;
    $this->actingAs(User::factory()->create());
});

it('uploads a file and creates an attachment record', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->original_name)->toBe('document.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->attachable_type)->toBe($member->getMorphClass())
        ->and($attachment->attachable_id)->toBe($member->id)
        ->and($attachment->uploaded_by)->toBe(auth()->id())
        ->and($attachment->disk)->toBe($this->disk);

    Storage::disk($this->disk)->assertExists($attachment->file_path);
});

it('uploads a file with category and description', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('contract.pdf', 2048, 'application/pdf');

    $attachment = $this->service->upload($file, $member, 'contracts', 'Main contract');

    expect($attachment->category)->toBe('contracts')
        ->and($attachment->description)->toBe('Main contract');
});

it('uploads an icon and generates a thumbnail', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    $result = $this->service->uploadIcon($file, $member);

    expect($result)->toHaveKeys(['icon_url', 'icon_thumb_url'])
        ->and($result['icon_url'])->toStartWith("icons/members/{$member->id}/")
        ->and($result['icon_thumb_url'])->toStartWith("icons/members/{$member->id}/thumbs/");

    Storage::disk($this->disk)->assertExists($result['icon_url']);
    Storage::disk($this->disk)->assertExists($result['icon_thumb_url']);
});

it('deletes an attachment and its files from storage', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('to-delete.pdf', 512, 'application/pdf');

    $attachment = $this->service->upload($file, $member);
    $path = $attachment->file_path;

    Storage::disk($this->disk)->assertExists($path);

    $this->service->delete($attachment);

    Storage::disk($this->disk)->assertMissing($path);
    expect(Attachment::find($attachment->id))->toBeNull();
});

it('deletes attachment with thumbnail', function () {
    $member = Member::factory()->create();
    Storage::disk($this->disk)->put('attachments/test.pdf', 'content');
    Storage::disk($this->disk)->put('attachments/thumbs/test.jpg', 'thumb');

    $attachment = Attachment::factory()->create([
        'attachable_type' => $member->getMorphClass(),
        'attachable_id' => $member->id,
        'file_path' => 'attachments/test.pdf',
        'thumb_path' => 'attachments/thumbs/test.jpg',
        'disk' => $this->disk,
    ]);

    $this->service->delete($attachment);

    Storage::disk($this->disk)->assertMissing('attachments/test.pdf');
    Storage::disk($this->disk)->assertMissing('attachments/thumbs/test.jpg');
});

it('generates a uuid for the attachment', function () {
    $member = Member::factory()->create();
    $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

    $attachment = $this->service->upload($file, $member);

    expect($attachment->uuid)->not->toBeNull()
        ->and($attachment->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});
