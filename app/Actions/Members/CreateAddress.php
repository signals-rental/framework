<?php

namespace App\Actions\Members;

use App\Data\Members\AddressData;
use App\Data\Members\CreateAddressData;
use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class CreateAddress
{
    public function __invoke(Member $member, CreateAddressData $data): AddressData
    {
        Gate::authorize('members.edit');

        $address = $member->addresses()->create($data->toArray());

        event(new AuditableEvent($member, 'member.address.created'));

        return AddressData::fromModel($address);
    }
}
