<?php

namespace App\Services\Calendar;

use App\Enums\ActivityStatus;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Hand-rolled RFC 5545 (iCalendar) document generator.
 *
 * Produces a single VCALENDAR document with one VEVENT per activity, using
 * CRLF line endings and 75-octet line folding. No external dependency.
 */
class IcsFeedBuilder
{
    /**
     * UTC date-time format in iCalendar basic form, e.g. 20260613T143000Z.
     */
    private const UTC_FORMAT = 'Ymd\THis\Z';

    /**
     * Date-only format for all-day events, e.g. 20260613.
     */
    private const DATE_FORMAT = 'Ymd';

    /**
     * Build a complete VCALENDAR document for the given activities.
     *
     * @param  Collection<int, Activity>  $activities
     */
    public function build(Collection $activities, string $calendarName): string
    {
        $host = $this->host();
        $dtstamp = now()->utc()->format(self::UTC_FORMAT);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Signals//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($calendarName),
        ];

        foreach ($activities as $activity) {
            foreach ($this->eventLines($activity, $host, $dtstamp) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * Build the VEVENT content lines for a single activity.
     *
     * @return list<string>
     */
    private function eventLines(Activity $activity, string $host, string $dtstamp): array
    {
        /** @var CarbonInterface $start */
        $start = $activity->starts_at;

        /** @var CarbonInterface|null $end */
        $end = $activity->ends_at;

        /** @var ActivityStatus|null $status */
        $status = $activity->status_id;

        /** @var TimeStatus|null $timeStatus */
        $timeStatus = $activity->time_status;

        $lines = [
            'BEGIN:VEVENT',
            'UID:activity-'.$activity->id.'@'.$host,
            'DTSTAMP:'.$dtstamp,
        ];

        if ($this->isAllDay($start, $end)) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$start->format(self::DATE_FORMAT);
            $lines[] = 'DTEND;VALUE=DATE:'.$this->allDayExclusiveEnd($start, $end)->format(self::DATE_FORMAT);
        } else {
            $finish = $end ?? $start->copy()->addMinutes(30);
            $lines[] = 'DTSTART:'.$start->copy()->utc()->format(self::UTC_FORMAT);
            $lines[] = 'DTEND:'.$finish->copy()->utc()->format(self::UTC_FORMAT);
        }

        $lines[] = 'SUMMARY:'.$this->escape((string) $activity->subject);

        if (filled($activity->location)) {
            $lines[] = 'LOCATION:'.$this->escape((string) $activity->location);
        }

        if (filled($activity->description)) {
            $lines[] = 'DESCRIPTION:'.$this->escape((string) $activity->description);
        }

        foreach ($this->statusLines($status) as $line) {
            $lines[] = $line;
        }

        $lines[] = 'TRANSP:'.($timeStatus === TimeStatus::Free ? 'TRANSPARENT' : 'OPAQUE');

        /** @var User|null $owner */
        $owner = $activity->relationLoaded('owner') ? $activity->owner : null;
        if ($owner !== null && filled($owner->email)) {
            $lines[] = 'ORGANIZER;CN="'.$this->quoteParam((string) $owner->name).'":mailto:'.$owner->email;
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Map the activity status to STATUS and any auxiliary property lines.
     *
     * @return list<string>
     */
    private function statusLines(?ActivityStatus $status): array
    {
        return match ($status) {
            ActivityStatus::Cancelled => ['STATUS:CANCELLED'],
            ActivityStatus::Completed => ['STATUS:CONFIRMED', 'X-COMPLETED:TRUE'],
            default => ['STATUS:CONFIRMED'],
        };
    }

    /**
     * All-day heuristic (D8): starts at 00:00 and ends null, at 23:59, or at a
     * later midnight (single- or multi-day). The exclusive DTEND is derived
     * separately in {@see allDayExclusiveEnd()} so multi-day spans are
     * preserved rather than collapsing to a single day.
     */
    private function isAllDay(CarbonInterface $start, ?CarbonInterface $end): bool
    {
        if ($start->format('H:i:s') !== '00:00:00') {
            return false;
        }

        if ($end === null) {
            return true;
        }

        if ($end->format('H:i') === '23:59') {
            return true;
        }

        return $this->isLaterMidnight($start, $end);
    }

    /**
     * Resolve the RFC 5545 exclusive end DATE for an all-day event.
     *
     * The three all-day branches in {@see isAllDay()} map to distinct ends so
     * that multi-day events span their real range rather than collapsing to a
     * single day:
     *  - $end null            → start + 1 day (single-day default).
     *  - 23:59 heuristic      → last day is $end's date, so exclusive end is
     *                           the following midnight ($end + 1 day).
     *  - midnight end         → $end is already the exclusive end date as-is.
     */
    private function allDayExclusiveEnd(CarbonInterface $start, ?CarbonInterface $end): CarbonInterface
    {
        if ($end === null) {
            return $start->copy()->addDay()->startOfDay();
        }

        if ($end->format('H:i:s') === '00:00:00') {
            return $end->copy()->startOfDay();
        }

        return $end->copy()->addDay()->startOfDay();
    }

    /**
     * Determine whether $end is exactly a midnight on a day after $start's date
     * (i.e. the inclusive next-midnight or a later one for multi-day events).
     */
    private function isLaterMidnight(CarbonInterface $start, CarbonInterface $end): bool
    {
        return $end->format('H:i:s') === '00:00:00'
            && $end->copy()->startOfDay()->greaterThan($start->copy()->startOfDay());
    }

    /**
     * Escape a text value per RFC 5545 (backslash, semicolon, comma, newlines).
     */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    }

    /**
     * Prepare a property parameter value for emission inside double quotes.
     *
     * Per RFC 5545 §3.2 a parameter value containing COMMA, SEMICOLON, COLON
     * or whitespace must be DQUOTE-enclosed (not backslash-escaped), and a
     * literal double-quote cannot appear inside a quoted value, so any are
     * stripped. The caller wraps the result in double quotes.
     */
    private function quoteParam(string $value): string
    {
        return str_replace('"', '', $value);
    }

    /**
     * Fold a content line so each physical line is at most 75 octets, with
     * continuation lines prefixed by a single space (RFC 5545 §3.1).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $char) {
            // Continuation lines reserve one octet for the leading space, so
            // the usable budget after the first line is 74 octets.
            $limit = $folded === '' ? 75 : 74;

            if (strlen($current) + strlen($char) > $limit) {
                $folded .= ($folded === '' ? '' : "\r\n ").$current;
                $current = '';
            }

            $current .= $char;
        }

        $folded .= ($folded === '' ? '' : "\r\n ").$current;

        return $folded;
    }

    /**
     * Resolve the host portion of the configured app URL, falling back to
     * "signals" when it cannot be parsed.
     */
    private function host(): string
    {
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'signals';
    }
}
