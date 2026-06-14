<?php

namespace App\Services\Calendar;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarEventService
{
    /**
     * Scheduled activities overlapping the [$from, $to] window, optionally
     * filtered to the given owner ids. Owner is eager-loaded; ordered by
     * starts_at.
     *
     * Overlap rule: starts_at IS NOT NULL AND starts_at <= $to AND
     * (ends_at IS NULL OR ends_at >= $from).
     *
     * @param  list<int>  $ownerIds
     * @return Collection<int, Activity>
     */
    public function scheduled(CarbonInterface $from, CarbonInterface $to, array $ownerIds = []): Collection
    {
        return $this->overlapping($from, $to, $ownerIds)
            ->with(['owner', 'type', 'regarding', 'participants.member' => fn ($query) => $query->withTrashed()->with('user')])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Activities with no scheduled start (starts_at IS NULL), optionally
     * owner-filtered. Owner is eager-loaded; newest first.
     *
     * @param  list<int>  $ownerIds
     * @return Collection<int, Activity>
     */
    public function unscheduled(array $ownerIds = []): Collection
    {
        $query = Activity::query()
            ->whereNull('starts_at')
            ->with(['owner', 'type', 'regarding', 'participants.member' => fn ($query) => $query->withTrashed()->with('user')])
            ->latest();

        if ($ownerIds !== []) {
            $query->whereIn('owned_by', $ownerIds);
        }

        return $query->get();
    }

    /**
     * Feed window (D5): scheduled activities from one year ago with no forward
     * cap, optionally scoped to a single owner. Owner is eager-loaded; ordered
     * by starts_at.
     *
     * @return Collection<int, Activity>
     */
    public function forFeed(?int $ownerId = null): Collection
    {
        $query = Activity::query()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now()->subYear())
            ->with('owner')
            ->orderBy('starts_at');

        if ($ownerId !== null) {
            $query->where('owned_by', $ownerId);
        }

        return $query->get();
    }

    /**
     * Aggregate counts for activities in the same overlap window as
     * scheduled(): distinct owners, total activities, and completed count.
     *
     * @param  list<int>  $ownerIds
     * @return array{staff: int, activities: int, completed: int}
     */
    public function rangeStats(CarbonInterface $from, CarbonInterface $to, array $ownerIds = []): array
    {
        $query = $this->overlapping($from, $to, $ownerIds);

        return [
            'staff' => (clone $query)->distinct()->count('owned_by'),
            'activities' => (clone $query)->count(),
            'completed' => (clone $query)->where('status_id', ActivityStatus::Completed->value)->count(),
        ];
    }

    /**
     * Base query for activities overlapping the [$from, $to] window with an
     * optional owner filter.
     *
     * @param  list<int>  $ownerIds
     * @return Builder<Activity>
     */
    private function overlapping(CarbonInterface $from, CarbonInterface $to, array $ownerIds): Builder
    {
        $query = Activity::query()
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $to)
            ->where(function (Builder $sub) use ($from): void {
                $sub->whereNull('ends_at')->orWhere('ends_at', '>=', $from);
            });

        if ($ownerIds !== []) {
            $query->whereIn('owned_by', $ownerIds);
        }

        return $query;
    }
}
