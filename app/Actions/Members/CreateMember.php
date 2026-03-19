<?php

namespace App\Actions\Members;

use App\Data\Members\CreateMemberData;
use App\Data\Members\MemberData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\Gate;

class CreateMember
{
    public function __invoke(CreateMemberData $data): MemberData
    {
        Gate::authorize('members.create');

        app(CustomFieldValidator::class)->validate('Member', $data->custom_fields, enforceRequired: true);

        $member = Member::create([
            'name' => $data->name,
            'membership_type' => $data->membership_type,
            'is_active' => $data->is_active,
            'description' => $data->description,
            'locale' => $data->locale,
            'default_currency_code' => $data->default_currency_code,
            'bookable' => $data->bookable,
            'location_type' => $data->location_type,
            'day_cost' => $data->day_cost,
            'hour_cost' => $data->hour_cost,
            'distance_cost' => $data->distance_cost,
            'flat_rate_cost' => $data->flat_rate_cost,
            'lawful_basis_type_id' => $data->lawful_basis_type_id,
            'sale_tax_class_id' => $data->sale_tax_class_id,
            'purchase_tax_class_id' => $data->purchase_tax_class_id,
            'tag_list' => $data->tag_list,
            'mapping_id' => $data->mapping_id,
            'account_number' => $data->account_number,
            'tax_number' => $data->tax_number,
            'is_cash' => $data->is_cash,
            'is_on_stop' => $data->is_on_stop,
            'rating' => $data->rating,
            'owned_by' => $data->owned_by,
            'price_category_id' => $data->price_category_id,
            'discount_category_id' => $data->discount_category_id,
            'invoice_term_id' => $data->invoice_term_id,
            'invoice_term_length' => $data->invoice_term_length,
            'peppol_id' => $data->peppol_id,
            'chamber_of_commerce_number' => $data->chamber_of_commerce_number,
            'global_location_number' => $data->global_location_number,
            'title' => $data->title,
            'department' => $data->department,
        ]);

        $member->syncCustomFields($data->custom_fields, applyDefaults: true);

        event(new AuditableEvent($member, 'member.created'));

        app(\App\Services\Api\WebhookService::class)->dispatch('member.created', [
            'member' => MemberData::fromModel($member)->toArray(),
        ]);

        return MemberData::fromModel($member);
    }
}
