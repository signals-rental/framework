<?php

namespace Tests\Feature\Auth;

use App\Mail\TemplatedEmail;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, PasswordResetNotification::class, function ($notification) use ($user) {
            $response = Volt::test('auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'password')
                ->set('password_confirmation', 'password')
                ->call('resetPassword');

            $response
                ->assertHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            return true;
        });
    }

    public function test_reset_notification_renders_branded_template_with_reset_url(): void
    {
        (new EmailTemplateSeeder)->run();

        $user = User::factory()->create(['name' => 'Jane Smith']);
        $notification = new PasswordResetNotification('test-token-123');

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(TemplatedEmail::class, $mail);
        $this->assertStringContainsString('Reset your password', $mail->subjectLine);
        $this->assertStringContainsString('Jane Smith', $mail->bodyHtml);
        $this->assertStringContainsString('sig-btn', $mail->bodyHtml);
        // The reset URL is built with the user's email as a query param.
        $this->assertStringContainsString(rawurlencode($user->email), $mail->bodyHtml);
    }

    public function test_reset_notification_falls_back_when_template_inactive(): void
    {
        (new EmailTemplateSeeder)->run();
        EmailTemplate::where('key', 'password_reset')->update(['is_active' => false]);

        $user = User::factory()->create();
        $notification = new PasswordResetNotification('test-token-123');

        $mail = $notification->toMail($user);

        // Falls back to Laravel default MailMessage rather than TemplatedEmail.
        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertStringContainsString('password reset', strtolower(implode(' ', $mail->introLines)));
        $this->assertNotEmpty($mail->actionUrl);
    }

    public function test_reset_notification_falls_back_when_template_missing(): void
    {
        // No EmailTemplateSeeder run: template does not exist.
        $user = User::factory()->create();
        $notification = new PasswordResetNotification('test-token-123');

        $mail = $notification->toMail($user);

        $this->assertInstanceOf(MailMessage::class, $mail);
    }
}
