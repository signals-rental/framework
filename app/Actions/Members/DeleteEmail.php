<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Email;
use Illuminate\Support\Facades\Gate;

class DeleteEmail
{
    public function __invoke(Email $email): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($email, 'member.email.deleted'));

        $email->delete();
    }
}
