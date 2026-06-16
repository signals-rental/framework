<?php

namespace App\Livewire\Concerns;

use App\Models\ActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds an audit-log timeline (most-recent entries, shaped for the
 * `<x-signals.timeline>` Blade component) for a model.
 *
 * Components opt in to a per-caller verb → colour map via {@see timelineColorMap()},
 * so an activity can colour `.completed` green while a member colours `.merged` blue.
 */
/** @phpstan-ignore trait.unused (used by Volt components in Blade files) */
trait HasAuditTimeline
{
    /** Number of recent audit-log entries shown on a record's timeline. */
    protected int $auditTimelineLimit = 15;

    /**
     * Most-recent audit-log entries for the given model, shaped for the timeline.
     *
     * @return Collection<int, array{title: string, meta: string, color: ?string, body: ?string}>
     */
    protected function auditTimelineFor(Model $model): Collection
    {
        return ActionLog::query()
            ->with('user')
            ->forEntity($model->getMorphClass(), (int) $model->getKey())
            ->latest('created_at')
            ->limit($this->auditTimelineLimit)
            ->get()
            ->map(fn (ActionLog $log): array => [
                'title' => Str::of($log->action)->replace(['.', '_'], ' ')->headline()->toString(),
                'meta' => $log->created_at?->diffForHumans() ?? '',
                'color' => $this->timelineColor($log->action),
                'body' => $log->user?->name ? "by {$log->user->name}" : null,
            ]);
    }

    /**
     * Map an audit action to a timeline dot colour for at-a-glance scanning.
     *
     * Resolves the first colour whose suffix list contains the action's suffix,
     * using the caller's {@see timelineColorMap()}.
     */
    protected function timelineColor(string $action): ?string
    {
        foreach ($this->timelineColorMap() as $color => $suffixes) {
            if (Str::endsWith($action, $suffixes)) {
                return $color;
            }
        }

        return null;
    }

    /**
     * The verb-suffix → colour map for this caller's timeline. Override per
     * component to colour domain-specific actions (e.g. `.merged`, `.completed`).
     *
     * @return array<string, list<string>>
     */
    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.restored'],
            'red' => ['.deleted'],
            'blue' => ['.updated'],
        ];
    }
}
