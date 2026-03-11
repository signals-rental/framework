<?php

namespace App\Actions\Members;

use App\Data\Members\CreateLinkData;
use App\Data\Members\LinkData;
use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class CreateLink
{
    public function __invoke(Member $member, CreateLinkData $data): LinkData
    {
        Gate::authorize('members.edit');

        $link = $member->links()->create($data->toArray());

        event(new AuditableEvent($member, 'member.link.created'));

        return LinkData::fromModel($link);
    }
}
