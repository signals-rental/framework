<?php

use App\Actions\Attachments\CreateAttachment;
use App\Actions\Attachments\DeleteAttachment;
use App\Data\Attachments\CreateAttachmentData;
use App\Models\Attachment;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

describe('CreateAttachment', function () {
    it('creates attachment record with file', function () {
        $member = Member::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

        $dto = CreateAttachmentData::from([
            'attachable_type' => 'Member',
            'attachable_id' => $member->id,
            'category' => 'contract',
            'description' => 'Signed contract',
        ]);

        $result = (new CreateAttachment)($dto, $file);

        expect($result->original_name)->toBe('doc.pdf');
        expect($result->category)->toBe('contract');
        expect($result->description)->toBe('Signed contract');
        expect(Attachment::where('attachable_id', $member->id)->exists())->toBeTrue();
    });

    it('throws for unknown attachable type', function () {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $dto = new CreateAttachmentData(
            attachable_type: 'UnknownModel',
            attachable_id: 1,
        );

        (new CreateAttachment)($dto, $file);
    })->throws(\InvalidArgumentException::class, 'Unknown attachable type');

    it('throws for non-existent attachable id', function () {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $dto = new CreateAttachmentData(
            attachable_type: 'Member',
            attachable_id: 99999,
        );

        (new CreateAttachment)($dto, $file);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('DeleteAttachment', function () {
    it('deletes attachment and files', function () {
        $member = Member::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

        $dto = CreateAttachmentData::from([
            'attachable_type' => 'Member',
            'attachable_id' => $member->id,
        ]);

        $result = (new CreateAttachment)($dto, $file);
        $attachment = Attachment::find($result->id);

        (new DeleteAttachment)($attachment);

        expect(Attachment::find($result->id))->toBeNull();
    });
});
