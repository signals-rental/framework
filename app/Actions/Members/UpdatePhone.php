<?php

namespace App\Actions\Members;

use App\Data\Members\PhoneData;
use App\Data\Members\UpdatePhoneData;
use App\Events\AuditableEvent;
use App\Models\Phone;
use Illuminate\Support\Facades\Gate;

class UpdatePhone
{
    public function __invoke(Phone $phone, UpdatePhoneData $data): PhoneData
    {
        Gate::authorize('members.edit');

        $phone->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($phone, 'member.phone.updated'));

        return PhoneData::fromModel($phone->fresh());
    }
}
