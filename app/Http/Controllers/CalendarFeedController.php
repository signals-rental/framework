<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use App\Services\Calendar\IcsFeedBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarFeedController extends Controller
{
    /**
     * Stream the global iCal feed of every scheduled activity.
     */
    public function global(Request $request): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $activities = app(CalendarEventService::class)->forFeed(null);
        $ics = app(IcsFeedBuilder::class)->build($activities, 'Signals — All Activities');

        return $this->feedResponse($ics);
    }

    /**
     * Stream the iCal feed scoped to a single owner's activities.
     */
    public function user(Request $request, User $user): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $activities = app(CalendarEventService::class)->forFeed($user->id);
        $ics = app(IcsFeedBuilder::class)->build($activities, "Signals — {$user->name}");

        return $this->feedResponse($ics);
    }

    /**
     * Wrap an ICS body in the standard text/calendar response.
     */
    private function feedResponse(string $ics): Response
    {
        return response($ics, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="signals-calendar.ics"');
    }
}
