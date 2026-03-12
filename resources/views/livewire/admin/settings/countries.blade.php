<?php

use App\Models\Country;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Title('Countries')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $countryId): void
    {
        Gate::authorize('settings.manage');

        $country = Country::findOrFail($countryId);
        $country->update(['is_active' => ! $country->is_active]);
    }

    public function with(): array
    {
        return [
            'countries' => Country::query()
                ->when($this->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%"))
                ->orderBy('name')
                ->paginate(50),
        ];
    }
}; ?>

<section class="w-full">
    <x-admin.layout group="data" title="Countries" description="Manage active countries.">

        {{-- Search --}}
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search countries..." />
        </div>

        <div class="s-table-wrap">
            <table class="s-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Currency Code</th>
                        <th>Phone Prefix</th>
                        <th>Default Timezone</th>
                        <th>Active</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($countries as $country)
                        <tr wire:key="country-{{ $country->id }}">
                            <td class="font-medium">{{ $country->code }}</td>
                            <td>{{ $country->name }}</td>
                            <td>{{ $country->currency_code }}</td>
                            <td>{{ $country->phone_prefix }}</td>
                            <td>{{ $country->default_timezone }}</td>
                            <td>
                                <button wire:click="toggleActive({{ $country->id }})"
                                        class="s-btn-ghost s-btn-xs">
                                    @if($country->is_active)
                                        <span class="s-badge s-badge-green">Active</span>
                                    @else
                                        <span class="s-badge s-badge-zinc">Inactive</span>
                                    @endif
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-[var(--text-muted)]">No countries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $countries->links() }}
        </div>
    </x-admin.layout>
</section>
