<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Anyone with member view permission can list attachments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('members.view');
    }

    /**
     * Anyone with member view permission can view an attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        return $user->can('members.view');
    }

    /**
     * Anyone with member edit permission can create attachments.
     */
    public function create(User $user): bool
    {
        return $user->can('members.edit');
    }

    /**
     * Owner of the upload or anyone with member edit permission can delete.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->uploaded_by === $user->id) {
            return true;
        }

        return $user->can('members.edit');
    }
}
