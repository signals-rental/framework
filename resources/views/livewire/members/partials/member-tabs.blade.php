<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('members.show', $member)],
        ['name' => 'addresses', 'label' => 'Addresses', 'route' => route('members.addresses', $member), 'count' => $member->addresses_count ?? $member->addresses->count()],
        ['name' => 'emails', 'label' => 'Emails', 'route' => route('members.emails', $member), 'count' => $member->emails_count ?? $member->emails->count()],
        ['name' => 'phones', 'label' => 'Phones', 'route' => route('members.phones', $member), 'count' => $member->phones_count ?? $member->phones->count()],
        ['name' => 'links', 'label' => 'Links', 'route' => route('members.links', $member), 'count' => $member->links_count ?? $member->links->count()],
        ['name' => 'custom-fields', 'label' => 'Custom Fields', 'route' => route('members.custom-fields', $member)],
        ['name' => 'relationships', 'label' => 'Relationships', 'route' => route('members.relationships', $member)],
    ]"
    :active="$activeTab"
/>
