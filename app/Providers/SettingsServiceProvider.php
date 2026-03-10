<?php

namespace App\Providers;

use App\Services\SettingsRegistry;
use App\Services\SettingsService;
use App\Settings\EmailSettings;
use App\Settings\SecuritySettings;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        $this->app->singleton(SettingsRegistry::class, function (): SettingsRegistry {
            $registry = new SettingsRegistry;

            $registry->register(new EmailSettings);
            $registry->register(new SecuritySettings);

            return $registry;
        });
    }

    public function boot(): void
    {
        if (config('signals.installed')) {
            try {
                app(SettingsService::class)->load();
                $this->configureMailFromSettings();
            } catch (\Illuminate\Database\QueryException) {
                // Expected during migrations before the settings table exists
            }
        }
    }

    /**
     * Override Laravel's mail configuration from stored email settings.
     */
    private function configureMailFromSettings(): void
    {
        $service = app(SettingsService::class);
        $mailer = $service->get('email.mailer');

        if (! $mailer || $mailer === 'log') {
            return;
        }

        config(['mail.default' => $mailer]);

        match ($mailer) {
            'smtp' => config([
                'mail.mailers.smtp.host' => $service->get('email.smtp_host'),
                'mail.mailers.smtp.port' => $service->get('email.smtp_port'),
                'mail.mailers.smtp.username' => $service->get('email.smtp_username'),
                'mail.mailers.smtp.password' => $service->get('email.smtp_password'),
                'mail.mailers.smtp.encryption' => $service->get('email.smtp_encryption') === 'none' ? null : $service->get('email.smtp_encryption'),
            ]),
            'ses' => config([
                'services.ses.key' => $service->get('email.ses_key'),
                'services.ses.secret' => $service->get('email.ses_secret'),
                'services.ses.region' => $service->get('email.ses_region'),
            ]),
            'mailgun' => config([
                'services.mailgun.domain' => $service->get('email.mailgun_domain'),
                'services.mailgun.secret' => $service->get('email.mailgun_secret'),
            ]),
            'postmark' => config([
                'services.postmark.token' => $service->get('email.postmark_token'),
            ]),
            default => logger()->warning("Unknown mail driver configured in settings: {$mailer}"),
        };

        $fromAddress = $service->get('email.from_address');
        $fromName = $service->get('email.from_name');

        if ($fromAddress) {
            config([
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName ?: config('app.name'),
            ]);
        }

        $replyTo = $service->get('email.reply_to_address');

        if ($replyTo) {
            config([
                'mail.reply_to.address' => $replyTo,
                'mail.reply_to.name' => $fromName ?: config('app.name'),
            ]);
        }
    }
}
