<?php

use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Member $member;

    public function mount(Member $member): void
    {
        $this->member = $member->loadCount(['addresses', 'emails', 'phones', 'links', 'organisations', 'contacts']);
    }

    public function rendering(View $view): void
    {
        $view->title($this->member->name . ' — Contacts');
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isOrganisation = $this->member->membership_type === MembershipType::Organisation
            || $this->member->membership_type === MembershipType::Venue;

        $typeOptions = collect(MembershipType::cases())
            ->mapWithKeys(fn (MembershipType $t): array => [$t->value => $t->label()])
            ->all();

        $columns = [
            ['key' => 'checkbox', 'type' => 'checkbox'],
            ['key' => 'avatar', 'label' => '', 'view' => 'livewire.members.partials.column-avatar'],
            ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text', 'view' => 'livewire.members.partials.column-name'],
            ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.members.partials.column-type'],
            ['key' => 'email', 'label' => 'Primary Email', 'view' => 'livewire.members.partials.column-email'],
            ['key' => 'phone', 'label' => 'Primary Phone', 'view' => 'livewire.members.partials.column-phone'],
            ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive'], 'view' => 'livewire.members.partials.column-status'],
            ['key' => 'actions', 'type' => 'actions'],
        ];

        $scopes = $isOrganisation
            ? ['contactsOf' => $this->member->id]
            : ['organisationsOf' => $this->member->id];

        $label = $isOrganisation ? 'contacts' : 'organisations';

        return [
            'columns' => $columns,
            'scopes' => $scopes,
            'isOrganisation' => $isOrganisation,
            'label' => $label,
        ];
    }
}; ?>

<section class="w-full">
    @include('livewire.members.partials.member-header', ['member' => $member, 'subpage' => 'Contacts'])
    @include('livewire.members.partials.member-tabs', ['member' => $member, 'activeTab' => 'contacts'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Member::class"
            :searchable="['name']"
            :with="['emails', 'phones']"
            :scopes="$scopes"
            default-sort="name"
            :empty-message="'No ' . $label . ' found.'"
            actions-view="livewire.members.partials.row-actions"
            :key="'member-contacts-' . $member->id"
        />
    </div>
</section>
