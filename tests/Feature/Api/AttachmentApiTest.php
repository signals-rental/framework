<?php

use App\Models\Attachment;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $default = config('filesystems.default', 'local');
    Storage::fake($default === 'local' ? 'public' : $default);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/members/{member}/attachments', function () {
    it('lists attachments for a member', function () {
        $member = Member::factory()->create();
        Attachment::factory()->count(3)->create([
            'attachable_type' => Member::class,
            'attachable_id' => $member->id,
        ]);

        $this->getJson("/api/v1/members/{$member->id}/attachments")
            ->assertOk()
            ->assertJsonStructure([
                'attachments' => [
                    '*' => ['id', 'uuid', 'original_name', 'mime_type', 'file_size'],
                ],
                'meta',
            ])
            ->assertJsonCount(3, 'attachments');
    });

    it('does not return attachments from other members', function () {
        $member = Member::factory()->create();
        $otherMember = Member::factory()->create();

        Attachment::factory()->create([
            'attachable_type' => Member::class,
            'attachable_id' => $member->id,
        ]);
        Attachment::factory()->create([
            'attachable_type' => Member::class,
            'attachable_id' => $otherMember->id,
        ]);

        $this->getJson("/api/v1/members/{$member->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'attachments');
    });
});

describe('GET /api/v1/attachments/{id}', function () {
    it('shows a single attachment', function () {
        $attachment = Attachment::factory()->create();

        $this->getJson("/api/v1/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('attachment.id', $attachment->id)
            ->assertJsonStructure([
                'attachment' => [
                    'id', 'uuid', 'original_name', 'mime_type', 'file_size',
                    'category', 'description', 'url', 'thumb_url',
                    'uploaded_by', 'created_at', 'updated_at',
                ],
            ]);
    });
});

describe('POST /api/v1/attachments', function () {
    it('uploads a file attachment', function () {
        $member = Member::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $this->postJson('/api/v1/attachments', [
            'file' => $file,
            'attachable_type' => 'Member',
            'attachable_id' => $member->id,
            'category' => 'document',
            'description' => 'Test upload',
        ])
            ->assertCreated()
            ->assertJsonPath('attachment.original_name', 'document.pdf')
            ->assertJsonPath('attachment.category', 'document')
            ->assertJsonPath('attachment.description', 'Test upload');

        expect(Attachment::where('attachable_id', $member->id)->exists())->toBeTrue();
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/attachments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'attachable_type', 'attachable_id']);
    });

    it('validates file size', function () {
        $member = Member::factory()->create();
        $file = UploadedFile::fake()->create('huge.pdf', 25000, 'application/pdf');

        $this->postJson('/api/v1/attachments', [
            'file' => $file,
            'attachable_type' => Member::class,
            'attachable_id' => $member->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });

    it('validates attachable_type must be valid', function () {
        $this->postJson('/api/v1/attachments', [
            'file' => UploadedFile::fake()->create('doc.pdf', 100),
            'attachable_type' => 'InvalidModel',
            'attachable_id' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachable_type']);
    });
});

describe('DELETE /api/v1/attachments/{id}', function () {
    it('deletes an attachment', function () {
        $member = Member::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->postJson('/api/v1/attachments', [
            'file' => $file,
            'attachable_type' => 'Member',
            'attachable_id' => $member->id,
        ])->assertCreated();

        $attachmentId = $response->json('attachment.id');

        $this->deleteJson("/api/v1/attachments/{$attachmentId}")
            ->assertNoContent();

        expect(Attachment::find($attachmentId))->toBeNull();
    });
});

describe('authentication', function () {
    it('requires authentication', function () {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/members/1/attachments')->assertUnauthorized();
        $this->postJson('/api/v1/attachments', [])->assertUnauthorized();
    });
});
