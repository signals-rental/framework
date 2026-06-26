<div>
    @if ($canAccess)
        <x-signals.panel title="Recent Opportunities" class="mb-8">
            <x-slot:headerActions>
                <a class="s-btn s-btn-sm s-btn-ghost" href="{{ route('opportunities.index') }}" wire:navigate>View All</a>
            </x-slot:headerActions>

            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Subject</th>
                            <th>Member</th>
                            <th>Status</th>
                            <th class="text-right">Value</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($opportunities as $opportunity)
                            @php
                                $status = $opportunity->statusEnum();
                            @endphp
                            <tr wire:key="recent-opp-{{ $opportunity->id }}">
                                <td>
                                    <a class="s-cell-link" href="{{ route('opportunities.show', $opportunity) }}" wire:navigate>
                                        {{ $opportunity->number ?? '#'.$opportunity->id }}
                                    </a>
                                </td>
                                <td>{{ $opportunity->subject }}</td>
                                <td>{{ $opportunity->member?->name ?? '—' }}</td>
                                <td>
                                    <span class="s-status {{ $status->isClosed() ? 's-status-zinc' : 's-status-green' }}">
                                        <span class="s-status-dot"></span> {{ $status->label() }}
                                    </span>
                                </td>
                                <td class="s-cell-amount">{{ $opportunity->formatMoneyCost('charge_total') }}</td>
                                <td>@localdate($opportunity->created_at)</td>
                            </tr>
                        @empty
                            <tr wire:key="recent-opp-empty">
                                <td colspan="6" class="text-center text-[13px] text-[var(--text-secondary)]" style="padding: 1.5rem;">
                                    No opportunities yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>
        </x-signals.panel>
    @endif
</div>
