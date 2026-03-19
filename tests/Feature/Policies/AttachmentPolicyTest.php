<?php

use App\Models\Attachment;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->policy = new AttachmentPolicy;
});

describe('viewAny', function () {
    it('allows users with members.view permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');

        expect($this->policy->viewAny($user))->toBeTrue();
    });

    it('denies users without members.view permission', function () {
        $user = User::factory()->create();

        expect($this->policy->viewAny($user))->toBeFalse();
    });
});

describe('view', function () {
    it('allows users with members.view permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');
        $attachment = Attachment::factory()->create();

        expect($this->policy->view($user, $attachment))->toBeTrue();
    });
});

describe('create', function () {
    it('allows users with members.edit permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies users without members.edit permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');

        expect($this->policy->create($user))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows uploader regardless of permissions', function () {
        $user = User::factory()->create();
        // No roles — no permissions
        $attachment = Attachment::factory()->create(['uploaded_by' => $user->id]);

        expect($this->policy->delete($user, $attachment))->toBeTrue();
    });

    it('allows non-uploader with members.edit permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $otherUser = User::factory()->create();
        $attachment = Attachment::factory()->create(['uploaded_by' => $otherUser->id]);

        expect($this->policy->delete($user, $attachment))->toBeTrue();
    });

    it('denies non-uploader without members.edit permission', function () {
        $user = User::factory()->create();
        $user->assignRole('Read Only');
        $otherUser = User::factory()->create();
        $attachment = Attachment::factory()->create(['uploaded_by' => $otherUser->id]);

        expect($this->policy->delete($user, $attachment))->toBeFalse();
    });
});
