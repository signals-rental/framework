<?php

namespace App\Providers;

use App\Contracts\Availability\AvailabilityDataPresence;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Contracts\Availability\AvailabilityStrategyContract;
use App\Models\User;
use App\Services\Activities\ActivityTypeList;
use App\Services\Availability\DatabaseAvailabilityDataPresence;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\PassThroughAvailabilityStrategy;
use App\Services\Availability\RecalculationPipeline;
use App\Services\Availability\SettingsAvailabilityResolutionProvider;
use App\Services\Availability\SlotCalculator;
use App\Services\AvailabilityService;
use App\Services\ColumnRegistryResolver;
use App\Services\DemandSourceDefinition;
use App\Services\DemandSourceRegistry;
use App\Services\DocsService;
use App\Services\NotificationRegistry;
use App\Services\PermissionRegistry;
use App\Services\RateEngine\Modifiers\FactorModifier;
use App\Services\RateEngine\Modifiers\MultiplierModifier;
use App\Services\RateEngine\RateEngineRegistry;
use App\Services\RateEngine\Strategies\FixedStrategy;
use App\Services\RateEngine\Strategies\HybridStrategy;
use App\Services\RateEngine\Strategies\PeriodStrategy;
use App\Services\SchemaRegistry;
use App\Services\Shortages\CostApportionmentRegistry;
use App\Services\Shortages\NullCostApportionmentStrategy;
use App\Services\Shortages\PipelineShortageEmitter;
use App\Services\Shortages\Resolvers\DateShiftResolver;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\QuoteReallocationResolver;
use App\Services\Shortages\Resolvers\SubstitutionResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
use App\Services\Shortages\ShortageResolverDefinition;
use App\Services\Shortages\ShortageResolverRegistry;
use App\Services\TaxCalculator;
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
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;
use Thunk\Verbs\Lifecycle\MetadataManager;
use Thunk\Verbs\Metadata;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Timezone::class);
        $this->app->singleton(Formatter::class);
        $this->app->singleton(DocsService::class);
        $this->app->singleton(SchemaRegistry::class);
        $this->app->singleton(TaxCalculator::class);
        $this->app->singleton(ColumnRegistryResolver::class);
        $this->app->singleton(ActivityTypeList::class);

        // Availability seams — tenant-ignorant OSS defaults. The commercial Cloud
        // package rebinds these to tenant-/hosting-aware implementations.
        $this->app->bind(AvailabilityDataPresence::class, DatabaseAvailabilityDataPresence::class);
        $this->app->bind(AvailabilityResolutionProvider::class, SettingsAvailabilityResolutionProvider::class);

        // Availability strategy hook seam — the OSS default is a pass-through
        // no-op bracketing the recalculation per-slot calculation (steps 3 & 5).
        // A plugin rebinds this to add buffer stock, sub-hire augmentation, etc.
        $this->app->bind(AvailabilityStrategyContract::class, PassThroughAvailabilityStrategy::class);

        // Availability engine read/recalc services. The SlotCalculator reads the
        // resolution live through its provider, so a singleton is safe.
        $this->app->singleton(SlotCalculator::class);
        $this->app->singleton(PipelineShortageEmitter::class);
        $this->app->singleton(RecalculationPipeline::class);
        $this->app->singleton(AvailabilityService::class);

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

        $this->app->singleton(DemandSourceRegistry::class, function (): DemandSourceRegistry {
            $registry = new DemandSourceRegistry;

            $registry->register(new DemandSourceDefinition(
                type: 'opportunity_item',
                displayName: 'Bookings',
                resolverClass: OpportunityItemDemandResolver::class,
                colour: '#3B82F6',
                icon: 'calendar',
            ));

            return $registry;
        });

        $this->app->singleton(RateEngineRegistry::class, function (): RateEngineRegistry {
            $registry = new RateEngineRegistry;

            $registry->registerStrategy(new PeriodStrategy);
            $registry->registerStrategy(new FixedStrategy);
            $registry->registerStrategy(new HybridStrategy);

            $registry->registerModifier(new MultiplierModifier);
            $registry->registerModifier(new FactorModifier);

            return $registry;
        });

        // Shortage resolvers — core registers the built-in non-PO resolvers;
        // plugins register their own (and the sub-hire/external-sourcing
        // resolvers land in Phase 4) through the same interface.
        $this->app->singleton(ShortageResolverRegistry::class, function (): ShortageResolverRegistry {
            $registry = new ShortageResolverRegistry;

            $registry->register(new ShortageResolverDefinition('reallocate', QuoteReallocationResolver::class));
            $registry->register(new ShortageResolverDefinition('substitute', SubstitutionResolver::class));
            $registry->register(new ShortageResolverDefinition('transfer', WarehouseTransferResolver::class));
            $registry->register(new ShortageResolverDefinition('date_shift', DateShiftResolver::class));
            $registry->register(new ShortageResolverDefinition('partial', PartialFulfilmentResolver::class));
            $registry->register(new ShortageResolverDefinition('waitlist', WaitlistResolver::class));

            return $registry;
        });

        // Cost apportionment — STUB for later sub-hire (Phase 4). Ships the no-op
        // strategy only; virtual stock and plugins register the real strategies.
        $this->app->singleton(CostApportionmentRegistry::class, function (): CostApportionmentRegistry {
            $registry = new CostApportionmentRegistry;

            $registry->register(new NullCostApportionmentStrategy);

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->registerBladeDirectives();
        $this->registerSocialiteProviders();
        $this->registerVerbsMetadata();

        // LogAction is auto-discovered by Laravel via its handle(AuditableEvent) type-hint.
        // Manual registration removed to prevent duplicate listener execution.
    }

    /**
     * Stamp every Verbs event with the firing actor's context at fire time.
     *
     * The callback runs once per event when it is first fired (reading live
     * auth()/request()), and the resulting metadata is serialised into the
     * verb_events.metadata column at commit. That persisted column is the
     * durable source of actor truth: the audit bridge reads it back (raw, by
     * event id) when projecting, so the ORIGINAL actor is preserved across
     * Verbs::replay() even under console/replay where auth()/request() are null.
     * Tenant-ignorant by design: actor context only, no business logic.
     */
    protected function registerVerbsMetadata(): void
    {
        app(MetadataManager::class)->createMetadataUsing(function (Metadata $metadata): array {
            $request = app()->runningInConsole() ? null : request();

            // Returning an iterable lets the MetadataManager merge() it onto the
            // event's Metadata, sidestepping direct writes to Metadata's magic
            // (undeclared) properties.
            return [
                'user_id' => auth()->id(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ];
        });
    }

    /**
     * Register community Socialite providers.
     *
     * Google ships with Laravel Socialite core, but Microsoft 365 requires the
     * socialiteproviders/microsoft package, which extends Socialite via the
     * SocialiteWasCalled event.
     */
    protected function registerSocialiteProviders(): void
    {
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);
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
     * Register Blade directives for localised date/datetime display and permission layers.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('localdate', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->date({$expression}); ?>";
        });

        Blade::directive('localdatetime', function (string $expression): string {
            return "<?php echo app(\App\Support\Formatter::class)->dateTime({$expression}); ?>";
        });

        // Permission layer directives
        Blade::directive('area', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endarea', function (): string {
            return '<?php endif; ?>';
        });

        Blade::directive('action', function (string $expression): string {
            return "<?php if(auth()->check() && auth()->user()->can({$expression})): ?>";
        });

        Blade::directive('endaction', function (): string {
            return '<?php endif; ?>';
        });

        Blade::directive('costs', function (): string {
            return "<?php if(auth()->check() && auth()->user()->can('costs.view')): ?>";
        });

        Blade::directive('endcosts', function (): string {
            return '<?php endif; ?>';
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
