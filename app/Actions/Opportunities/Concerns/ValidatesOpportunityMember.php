<?php

namespace App\Actions\Opportunities\Concerns;

use App\Enums\MembershipType;
use App\Models\Address;
use App\Models\Member;
use Illuminate\Validation\ValidationException;

/**
 * Shared authoritative customer-type and address-ownership guards for the
 * opportunity create/update actions.
 *
 * Both guards back the DTO's scoped validation rules, but ::rules() is called
 * context-free on the manual validate() path (and a caller can spoof/omit
 * member_id past it), so these are the REAL gates run inside the action.
 */
trait ValidatesOpportunityMember
{
    /**
     * Assert that the supplied opportunity customer (when one is set) is an
     * Organisation member. A Contact/User/Venue — or a missing member — is rejected
     * with a 422 so an opportunity cannot be created against a non-organisation
     * customer. A null member_id is allowed (the header customer is optional / "leave
     * unchanged" on the update path).
     */
    protected function assertMemberIsOrganisation(?int $memberId): void
    {
        if ($memberId === null) {
            return;
        }

        $isOrganisation = Member::query()
            ->whereKey($memberId)
            ->where('membership_type', MembershipType::Organisation->value)
            ->exists();

        if (! $isOrganisation) {
            throw ValidationException::withMessages([
                'member_id' => ['The opportunity customer must be an organisation.'],
            ]);
        }
    }

    /**
     * Assert that a single supplied address FK points at an {@see Address} owned by
     * the given member (polymorphic addressable_type = Member, addressable_id =
     * member_id). Closes the IDOR where a caller targets another member's address. A
     * null address id is skipped (nothing to leak). A mismatch — including a
     * non-Member-owned address, or any address when no member is set — is surfaced as
     * a 422 rather than silently dropped.
     */
    protected function assertAddressBelongsToMember(string $field, ?int $addressId, ?int $memberId): void
    {
        if ($addressId === null) {
            return;
        }

        $belongs = $memberId !== null && Address::query()
            ->whereKey($addressId)
            ->where('addressable_type', Member::class)
            ->where('addressable_id', $memberId)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                $field => ['The selected address does not belong to this opportunity\'s member.'],
            ]);
        }
    }
}
