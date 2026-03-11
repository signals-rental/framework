<?php

namespace App\Actions\Members;

use App\Data\Members\LinkData;
use App\Data\Members\UpdateLinkData;
use App\Events\AuditableEvent;
use App\Models\Link;
use Illuminate\Support\Facades\Gate;

class UpdateLink
{
    public function __invoke(Link $link, UpdateLinkData $data): LinkData
    {
        Gate::authorize('members.edit');

        $link->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($link, 'member.link.updated'));

        return LinkData::fromModel($link->fresh());
    }
}
