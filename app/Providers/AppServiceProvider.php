<?php

namespace App\Providers;

use App\Events\AuditableEvent;
use App\Listeners\LogAction;
use App\Models\User;
use App\Services\DocsService;
use App\Services\NotificationRegistry;
use App\Services\PermissionRegistry;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
}
