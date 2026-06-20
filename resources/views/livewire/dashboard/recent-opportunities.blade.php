<div>
    @if ($canAccess)
        <div class="data-table-wrap mb-8">
            <div class="table-header">
                <span class="table-title">Recent Opportunities</span>
                <a class="table-action" href="{{ route('opportunities.index') }}" wire:navigate>View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
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
                                    <a class="cell-ref" href="{{ route('opportunities.show', $opportunity) }}" wire:navigate>
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
                                <td class="cell-amount">{{ $opportunity->formatMoneyCost('charge_total') }}</td>
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
            </div>
        </div>
    @endif
</div>
