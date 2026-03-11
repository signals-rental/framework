<?php

namespace App\Actions\Members;

use App\Data\Members\AddressData;
use App\Data\Members\UpdateAddressData;
use App\Events\AuditableEvent;
use App\Models\Address;
use Illuminate\Support\Facades\Gate;

class UpdateAddress
{
    public function __invoke(Address $address, UpdateAddressData $data): AddressData
    {
        Gate::authorize('members.edit');

        $address->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($address, 'member.address.updated'));

        return AddressData::fromModel($address->fresh());
    }
}
