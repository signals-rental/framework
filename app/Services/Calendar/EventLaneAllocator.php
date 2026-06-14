<?php

namespace App\Services\Calendar;

class EventLaneAllocator
{
    /**
     * Assign horizontal lanes to timed events so overlapping events render
     * side-by-side.
     *
     * Each input event is an associative array carrying at least `start_min`
     * (int, minutes from the window start) and `end_min` (int). Any additional
     * keys (e.g. `id`) are preserved. Every returned event is augmented with:
     *  - `lane`  (int): 0-based column index within its overlap cluster.
     *  - `lanes` (int): total concurrent columns in its cluster (>= 1).
     *
     * The original input order is preserved in the returned array. Events that
     * merely touch (one ends exactly when the next begins) are not considered
     * overlapping.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public function allocate(array $events): array
    {
        if ($events === []) {
            return [];
        }

        // Work on indexed copies so we can restore the caller's original order.
        $indexed = [];
        foreach ($events as $originalIndex => $event) {
            $indexed[] = [
                'original_index' => $originalIndex,
                'event' => $event,
                'start_min' => (int) $event['start_min'],
                'end_min' => (int) $event['end_min'],
                'lane' => 0,
                'lanes' => 1,
            ];
        }

        // Sort by start, then end, so greedy placement is stable.
        usort($indexed, function (array $a, array $b): int {
            return $a['start_min'] <=> $b['start_min']
                ?: $a['end_min'] <=> $b['end_min'];
        });

        /** @var list<int> $laneEnds end_min of the last event placed in each lane */
        $laneEnds = [];
        /** @var list<int> $clusterMembers indices (into $indexed) of the current cluster */
        $clusterMembers = [];
        $clusterEnd = null;

        foreach ($indexed as $i => &$item) {
            // A new cluster starts when this event begins at or after the point
            // where every event so far has finished (no transitive overlap).
            if ($clusterEnd === null || $item['start_min'] >= $clusterEnd) {
                $this->finaliseCluster($indexed, $clusterMembers, count($laneEnds));
                $laneEnds = [];
                $clusterMembers = [];
                $clusterEnd = $item['end_min'];
            }

            // Greedily place in the first lane whose last event ends <= start.
            $placed = false;
            foreach ($laneEnds as $lane => $laneEnd) {
                if ($laneEnd <= $item['start_min']) {
                    $item['lane'] = $lane;
                    $laneEnds[$lane] = $item['end_min'];
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                $item['lane'] = count($laneEnds);
                $laneEnds[] = $item['end_min'];
            }

            $clusterMembers[] = $i;
            $clusterEnd = max($clusterEnd, $item['end_min']);
        }
        unset($item);

        $this->finaliseCluster($indexed, $clusterMembers, count($laneEnds));

        // Restore original order and merge lane/lanes back into the events.
        usort($indexed, fn (array $a, array $b): int => $a['original_index'] <=> $b['original_index']);

        return array_map(
            fn (array $item): array => $item['event'] + ['lane' => $item['lane'], 'lanes' => $item['lanes']],
            $indexed
        );
    }

    /**
     * Stamp every member of a completed cluster with the cluster's lane count.
     *
     * @param  array<int, array<string, mixed>>  $indexed
     * @param  list<int>  $memberIndices
     */
    private function finaliseCluster(array &$indexed, array $memberIndices, int $laneCount): void
    {
        if ($laneCount < 1) {
            return;
        }

        foreach ($memberIndices as $index) {
            $indexed[$index]['lanes'] = $laneCount;
        }
    }
}
