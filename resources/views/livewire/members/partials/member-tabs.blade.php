<x-signals.module-tabs
    :tabs="[
        ['name' => 'overview', 'label' => 'Overview', 'route' => route('members.show', $member)],
        ['name' => 'information', 'label' => 'Information', 'route' => route('members.information', $member)],
        ['name' => 'contacts', 'label' => 'Contacts', 'route' => route('members.contacts', $member), 'count' => $member->contacts_count ?? $member->organisations_count ?? 0],
        ['name' => 'activities', 'label' => 'Activities', 'route' => route('members.activities', $member), 'count' => 0],
        ['name' => 'opportunities', 'label' => 'Opportunities', 'route' => route('members.opportunities', $member), 'count' => 0],
        ['name' => 'movements', 'label' => 'Movements', 'route' => route('members.movements', $member), 'count' => 0],
        ['name' => 'invoices', 'label' => 'Invoices', 'route' => route('members.invoices', $member), 'count' => 0],
        ['name' => 'files', 'label' => 'Files', 'route' => route('members.files', $member), 'count' => $member->attachments_count ?? 0],
    ]"
    :active="$activeTab"
/>
