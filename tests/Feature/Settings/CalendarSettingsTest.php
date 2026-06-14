<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CalendarSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    }

    public function test_calendar_settings_page_is_displayed_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.calendar'))->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('settings.calendar'))->assertRedirect(route('login'));
    }

    public function test_page_shows_the_current_users_valid_signed_feed_url(): void
    {
        $this->actingAs($user = User::factory()->create());

        $expected = URL::signedRoute('calendar.feed.user', ['user' => $user->id]);

        $response = $this->get(route('settings.calendar'))->assertOk();

        // The exact signed URL for this user is rendered on the page.
        $response->assertSee($expected, escape: false);

        // ...and the route path is present with a signature query parameter.
        $response->assertSee('calendar/feed/'.$user->id.'.ics', escape: false);
        $response->assertSee('signature=', escape: false);

        // The rendered URL is a genuinely valid signature.
        $this->assertTrue($this->signatureFor($response->getContent(), $user->id)->isValid());
    }

    public function test_non_admin_does_not_see_the_global_feed_url(): void
    {
        $this->actingAs(User::factory()->create());

        $globalUrl = URL::signedRoute('calendar.feed.global');

        $this->get(route('settings.calendar'))
            ->assertOk()
            ->assertDontSee('calendar/feed.ics', escape: false)
            ->assertDontSee('Global feed');
    }

    public function test_admin_sees_the_global_feed_url(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee('calendar/feed.ics', escape: false)
            ->assertSee('Global feed');
    }

    public function test_owner_sees_the_global_feed_url(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $this->get(route('settings.calendar'))
            ->assertOk()
            ->assertSee('calendar/feed.ics', escape: false);
    }

    /**
     * Pull the rendered per-user feed URL out of the HTML and wrap it in a
     * tiny request so the signature can be validated against Laravel's signer.
     */
    private function signatureFor(string $html, int $userId): SignatureProbe
    {
        $needle = 'calendar/feed/'.$userId.'.ics';

        $this->assertStringContainsString($needle, $html);

        // Find the full URL (scheme through to the end of the query string).
        preg_match('#https?://[^"\'\s]*'.preg_quote($needle, '#').'\?[^"\'\s]*#', $html, $matches);

        $this->assertNotEmpty($matches, 'A signed per-user feed URL should be present in the page.');

        return new SignatureProbe($matches[0]);
    }
}

/**
 * Minimal helper that validates a signed URL's signature.
 */
class SignatureProbe
{
    public function __construct(private string $url) {}

    public function isValid(): bool
    {
        $request = Request::create($this->url);

        return $request->hasValidSignature();
    }
}
