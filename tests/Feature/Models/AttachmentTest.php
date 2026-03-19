<?php

use App\Models\Attachment;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('creates an attachment with factory', function () {
    $attachment = Attachment::factory()->create();

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->uuid)->not->toBeNull()
        ->and($attachment->file_path)->not->toBeNull();
});

it('generates a uuid on creation', function () {
    $member = Member::factory()->create();

    $attachment = Attachment::create([
        'attachable_type' => $member->getMorphClass(),
        'attachable_id' => $member->id,
        'original_name' => 'test.pdf',
        'file_path' => 'attachments/test.pdf',
        'disk' => 's3',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
    ]);

    expect($attachment->uuid)->not->toBeNull()
        ->and($attachment->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('belongs to an attachable entity via morph', function () {
    $member = Member::factory()->create();
    $attachment = Attachment::factory()->create([
        'attachable_type' => $member->getMorphClass(),
        'attachable_id' => $member->id,
    ]);

    expect($attachment->attachable)->toBeInstanceOf(Member::class)
        ->and($attachment->attachable->getKey())->toBe($member->id);
});

it('belongs to an uploaded by user', function () {
    $user = User::factory()->create();
    $attachment = Attachment::factory()->create([
        'uploaded_by' => $user->id,
    ]);

    expect($attachment->uploadedBy)->toBeInstanceOf(User::class)
        ->and($attachment->uploadedBy->id)->toBe($user->id);
});

it('casts file_size to integer', function () {
    $attachment = Attachment::factory()->create(['file_size' => '2048']);

    expect($attachment->file_size)->toBeInt()->toBe(2048);
});

it('supports the image factory state', function () {
    $attachment = Attachment::factory()->image()->create();

    expect($attachment->mime_type)->toBe('image/jpeg')
        ->and($attachment->thumb_path)->not->toBeNull();
});

it('thumbUrl returns null when thumb_path is null', function () {
    $attachment = Attachment::factory()->create(['thumb_path' => null]);

    expect($attachment->thumbUrl())->toBeNull();
});

it('url method calls temporaryUrl on the storage disk', function () {
    $attachment = Attachment::factory()->create([
        'disk' => 's3',
        'file_path' => 'attachments/test.pdf',
    ]);

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturnSelf();

    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->andReturn('https://s3.example.com/signed-url');

    $url = $attachment->url();

    expect($url)->toBe('https://s3.example.com/signed-url');
});

it('thumbUrl method calls temporaryUrl when thumb_path is set', function () {
    $attachment = Attachment::factory()->image()->create([
        'disk' => 's3',
        'thumb_path' => 'attachments/thumbs/test.jpg',
    ]);

    Storage::shouldReceive('disk')
        ->with('s3')
        ->andReturnSelf();

    Storage::shouldReceive('temporaryUrl')
        ->once()
        ->andReturn('https://s3.example.com/signed-thumb-url');

    $url = $attachment->thumbUrl();

    expect($url)->toBe('https://s3.example.com/signed-thumb-url');
});
