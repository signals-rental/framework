<?php

namespace App\Providers;

use App\Events\AuditableEvent;
use App\Listeners\LogAction;
use App\Models\User;
use App\Services\DocsService;
use App\Services\NotificationRegistry;
use App\Services\PermissionRegistry;
use App\Support\Formatter;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Timezone::class);
        $this->app->singleton(Formatter::class);
        $this->app->singleton(DocsService::class);

        $this->app->singleton(PermissionRegistry::class, function (): PermissionRegistry {
            $registry = new PermissionRegistry;
            $registry->registerMany(PermissionSeeder::permissions());

            return $registry;
        });

        $this->app->singleton(NotificationRegistry::class, function (): NotificationRegistry {
            $registry = new NotificationRegistry;
            $registry->registerMany(NotificationTypeSeeder::types());

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->registerBladeDirectives();

        Event::listen(AuditableEvent::class, LogAction::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): ?Password {
            if (! app()->isProduction()) {
                return null;
            }

            $minLength = max(8, (int) settings('security.password_min_length', 8));
            $rule = Password::min($minLength);

            if (settings('security.password_require_uppercase', false)) {
                $rule->mixedCase();
            }

            if (settings('security.password_require_number', false)) {
                $rule->numbers();
            }

            if (settings('security.password_require_special', false)) {
                $rule->symbols();
            }

            return $rule->uncompromised();
        });
    }

    /**
     * Owner users bypass all permission checks.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->isOwner() ? true : null;
        });

        Gate::define('owner', function (User $user): bool {
            return $user->isOwner();
        });
    }

    /**
     * Register Blade directives for localised date/datetime display.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('localdate', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->date({$expression}); ?>";
        });

        Blade::directive('localdatetime', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->dateTime({$expression}); ?>";
        });
    }

    /**
     * Configure API rate limiting using settings-driven limits.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function ($request) {
            try {
                $limit = max((int) settings('api.rate_limit', 60), 1);
                $unauthLimit = max((int) settings('api.rate_limit_unauthenticated', 20), 1);
            } catch (\Throwable) {
                $limit = 60;
                $unauthLimit = 20;
            }

            if (! $request->user()) {
                return Limit::perMinute($unauthLimit)->by($request->ip());
            }

            $token = $request->user()->currentAccessToken();

            if ($token instanceof PersonalAccessToken && $token->rate_limit_per_minute) {
                return Limit::perMinute($token->rate_limit_per_minute)->by('token:'.$token->id);
            }

            return Limit::perMinute($limit)->by($request->user()->id);
        });
    }
}
