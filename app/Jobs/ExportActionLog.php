<?php

namespace App\Jobs;

use App\Models\ActionLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportActionLog implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public int $userId,
        public array $filters = [],
    ) {
        $this->queue = 'exports';
    }

    public function handle(): void
    {
        $query = ActionLog::query()->with('user')->latest('created_at');

        if (! empty($this->filters['action'])) {
            $query->where('action', $this->filters['action']);
        }

        if (! empty($this->filters['auditable_type'])) {
            $query->where('auditable_type', $this->filters['auditable_type']);
        }

        if (! empty($this->filters['date_from'])) {
            $query->where('created_at', '>=', $this->filters['date_from']);
        }

        if (! empty($this->filters['date_to'])) {
            $query->where('created_at', '<=', $this->filters['date_to'].' 23:59:59');
        }

        $filename = 'exports/action-log-'.Str::uuid().'.csv';
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['ID', 'Action', 'Entity Type', 'Entity ID', 'User', 'IP Address', 'Date']);

        $query->chunk(500, function ($logs) use ($handle) {
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->action,
                    $log->auditable_type ? class_basename($log->auditable_type) : '',
                    $log->auditable_id,
                    $log->user ? $log->user->name : 'System',
                    $log->ip_address,
                    $log->created_at?->toIso8601String(),
                ]);
            }
        });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::put($filename, $csv);

        $cacheKey = "action-log-export:{$this->userId}";
        Cache::put($cacheKey, $filename, now()->addHours(1));
    }
}
