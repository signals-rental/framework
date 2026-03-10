<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransferOwnership
{
    public function __invoke(User $newOwner): void
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();

        if (! $currentUser->isOwner()) {
            throw ValidationException::withMessages([
                'user' => 'Only the current owner can transfer ownership.',
            ]);
        }

        if ($newOwner->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => 'You are already the owner.',
            ]);
        }

        if (! $newOwner->isActive()) {
            throw ValidationException::withMessages([
                'user' => 'Cannot transfer ownership to a deactivated user.',
            ]);
        }

        // Remove ownership from current user
        $currentUser->update(['is_owner' => false]);

        // Transfer to new owner
        $newOwner->update([
            'is_owner' => true,
            'is_admin' => true,
        ]);
    }
}
