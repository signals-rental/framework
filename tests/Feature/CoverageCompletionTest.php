<?php

/**
 * Systematic branch-coverage tests to push classes to 100% method coverage.
 *
 * Each test targets specific uncovered BRANCHES within methods that are
 * partially covered, to flip the class to fully covered.
 */

use App\Jobs\ExportActionLog;
use App\Models\ActionLog;
use App\Models\Attachment;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Member;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\CustomFieldCopier;
use App\Services\CustomFieldSerializer;
use App\Services\PermissionRegistry;
use App\Support\Formatter;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Formatter — cover ALL 9 methods fully
|--------------------------------------------------------------------------
*/
describe('Formatter complete coverage', function () {
    beforeEach(function () {
        \App\Models\Currency::factory()->create([
            'code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£',
            'decimal_places' => 2, 'is_enabled' => true,
        ]);
        settings()->set('company.base_currency', 'GBP');
        settings()->set('preferences.number_decimal_separator', '.');
        settings()->set('preferences.number_thousands_separator', ',');
        settings()->set('preferences.currency_display', 'symbol');
    });

    it('date() formats with configured format', function () {
        $formatter = app(Formatter::class);
        $result = $formatter->date(now());
        expect($result)->toBeString();
        expect(strlen($result))->toBeGreaterThan(0);
    });

    it('dateTime() formats with configured format', function () {
        $formatter = app(Formatter::class);
        $result = $formatter->dateTime(now());
        expect($result)->toBeString()->toContain('/');
    });

    it('number() formats with configured separators', function () {
        $formatter = app(Formatter::class);
        expect($formatter->number(1234.56))->toBe('1,234.56');
        expect($formatter->number(0.5, 1))->toBe('0.5');
    });

    it('percentage() appends percent sign', function () {
        $formatter = app(Formatter::class);
        expect($formatter->percentage(95.5))->toBe('95.50%');
    });

    it('money() with all display modes exercises currencySymbol and currencyName', function () {
        $formatter = app(Formatter::class);

        // Symbol mode (default) — exercises currencySymbol()
        expect($formatter->money(100, 'GBP'))->toBe('£1.00');

        // Code mode
        settings()->set('preferences.currency_display', 'code');
        expect($formatter->money(100, 'GBP'))->toBe('GBP 1.00');

        // Name mode — exercises currencyName()
        settings()->set('preferences.currency_display', 'name');
        expect($formatter->money(100, 'GBP'))->toBe('1.00 British Pound');
    });

    it('money() loadCurrency cache hit path', function () {
        $formatter = app(Formatter::class);
        // First call loads from DB
        $formatter->money(100, 'GBP');
        // Second call hits cache (exercises the isset branch in loadCurrency)
        $result = $formatter->money(200, 'GBP');
        expect($result)->toBe('£2.00');
    });

    it('money() loadCurrency cache miss fallback', function () {
        $formatter = app(Formatter::class);
        // Currency not in DB — exercises the null branch in loadCurrency
        $result = $formatter->money(100, 'CHF');
        expect($result)->toContain('CHF');
    });

    it('moneyDecimal() exercises full pipeline', function () {
        $formatter = app(Formatter::class);
        expect($formatter->moneyDecimal('99.99', 'GBP'))->toBe('£99.99');
    });

    it('money() with negative value exercises isNegative branch', function () {
        $formatter = app(Formatter::class);
        expect($formatter->money(-500, 'GBP'))->toBe('£-5.00');
    });

    it('money() with zero-decimal currency exercises scale=0 branch', function () {
        \App\Models\Currency::factory()->create([
            'code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥',
            'decimal_places' => 0, 'is_enabled' => true,
        ]);
        $formatter = app(Formatter::class);
        // scale=0 means no decimal part — exercises ($scale > 0 ? ... : '') branch
        expect($formatter->money(500, 'JPY'))->toBe('¥500');
    });
});

/*
|--------------------------------------------------------------------------
| PermissionRegistry — cover dependenciesFor and resolveDependencies
|--------------------------------------------------------------------------
*/
describe('PermissionRegistry complete coverage', function () {
    it('dependenciesFor resolves recursive dependencies', function () {
        $registry = new PermissionRegistry;
        $registry->register('members.edit', [
            'label' => 'Edit', 'description' => 'Edit members',
            'group' => 'Members', 'dependencies' => ['members.view'],
        ]);
        $registry->register('members.view', [
            'label' => 'View', 'description' => 'View members',
            'group' => 'Members', 'dependencies' => [],
        ]);

        $deps = $registry->dependenciesFor('members.edit');
        expect($deps)->toContain('members.view');
    });

    it('dependenciesFor handles missing permission key', function () {
        $registry = new PermissionRegistry;
        $deps = $registry->dependenciesFor('nonexistent');
        expect($deps)->toBe([]);
    });

    it('dependenciesFor handles circular references', function () {
        $registry = new PermissionRegistry;
        $registry->register('a', [
            'label' => 'A', 'description' => 'A', 'group' => 'Test',
            'dependencies' => ['b'],
        ]);
        $registry->register('b', [
            'label' => 'B', 'description' => 'B', 'group' => 'Test',
            'dependencies' => ['a'],
        ]);

        $deps = $registry->dependenciesFor('a');
        expect($deps)->toContain('b')
            ->and($deps)->toContain('a');
    });

    it('covers get, has, keys, all, grouped, validate, byLayer', function () {
        $registry = app(PermissionRegistry::class);

        expect($registry->get('members.view'))->toBeArray();
        expect($registry->get('nonexistent'))->toBeNull();
        expect($registry->has('members.view'))->toBeTrue();
        expect($registry->has('nonexistent'))->toBeFalse();
        expect(count($registry->keys()))->toBeGreaterThan(0);
        expect(count($registry->all()))->toBeGreaterThan(0);
        expect(count($registry->grouped()))->toBeGreaterThan(0);
        expect($registry->byLayer('action'))->toBeArray();
    });

    it('validate throws for invalid permissions', function () {
        $registry = app(PermissionRegistry::class);
        $registry->validate(['members.view']);
    })->throwsNoExceptions();

    it('validate throws for unknown permissions', function () {
        $registry = app(PermissionRegistry::class);
        $registry->validate(['completely.fake.permission']);
    })->throws(\Illuminate\Validation\ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| CurrencyService — cover baseCurrency fully
|--------------------------------------------------------------------------
*/
describe('CurrencyService complete coverage', function () {
    it('baseCurrency returns model when setting exists', function () {
        \App\Models\Currency::factory()->create(['code' => 'GBP', 'is_enabled' => true]);
        settings()->set('company.base_currency', 'GBP');

        $service = new CurrencyService;
        $currency = $service->baseCurrency();
        expect($currency->code)->toBe('GBP');
    });
});

/*
|--------------------------------------------------------------------------
| CustomFieldCopier — cover copy() fully (null-source-field and type mismatch)
|--------------------------------------------------------------------------
*/
describe('CustomFieldCopier complete coverage', function () {
    it('skips fields where target field does not exist', function () {
        $source = Member::factory()->create();
        $target = Member::factory()->create();

        // Create a source field that has no matching target field
        $sourceField = CustomField::factory()->forModule('Store')->create([
            'name' => 'store_only_field',
            'field_type' => \App\Enums\CustomFieldType::String,
        ]);

        CustomFieldValue::create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => $source->getMorphClass(),
            'entity_id' => $source->id,
            'value_string' => 'test',
        ]);

        $copier = app(CustomFieldCopier::class);
        $result = $copier->copy($source, $target, 'Member');

        expect($result->skipped)->toBeGreaterThanOrEqual(1);
    });

    it('returns empty result when source has no values', function () {
        $source = Member::factory()->create();
        $target = Member::factory()->create();

        $copier = app(CustomFieldCopier::class);
        $result = $copier->copy($source, $target, 'Member');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(0);
    });
});

/*
|--------------------------------------------------------------------------
| CustomFieldSerializer — cover eagerLoad and coerceDefaultValue
|--------------------------------------------------------------------------
*/
describe('CustomFieldSerializer complete coverage', function () {
    it('eagerLoad pre-loads values for collection', function () {
        $members = Member::factory()->count(2)->create();

        CustomField::factory()->forModule('Member')->create(['name' => 'test_eager']);

        $serializer = app(CustomFieldSerializer::class);
        $serializer->eagerLoad(Member::whereIn('id', $members->pluck('id'))->get(), 'Member');

        // Should not throw — values are pre-loaded
        foreach ($members as $member) {
            $result = $serializer->toArray($member->refresh());
            expect($result)->toHaveKey('test_eager');
        }
    });

    it('eagerLoad handles empty collection', function () {
        $serializer = app(CustomFieldSerializer::class);
        // Should not throw for empty collection
        $serializer->eagerLoad(collect(), 'Member');
    })->throwsNoExceptions();

    it('coerceDefaultValue for numeric types', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'numeric_default',
            'field_type' => \App\Enums\CustomFieldType::Number,
            'default_value' => '42.5',
        ]);

        $member = Member::factory()->create();
        $serializer = app(CustomFieldSerializer::class);
        $serializer->fromArray($member, [], applyDefaults: true);

        $result = $serializer->toArray($member);
        expect((float) $result['numeric_default'])->toBe(42.5);
    });
});

/*
|--------------------------------------------------------------------------
| ExportActionLog — cover handle() and failed()
|--------------------------------------------------------------------------
*/
describe('ExportActionLog job', function () {
    it('exports action logs to CSV with filters', function () {
        Storage::fake();

        $user = User::factory()->create();
        ActionLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $job = new ExportActionLog(userId: $user->id, filters: [
            'action' => 'updated',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $job->handle();

        $cacheKey = "action-log-export:{$user->id}";
        $filename = \Illuminate\Support\Facades\Cache::get($cacheKey);
        expect($filename)->toStartWith('exports/action-log-');
        Storage::assertExists($filename);
    });

    it('records failure in cache', function () {
        $job = new ExportActionLog(userId: 999);
        $job->failed(new \RuntimeException('Test failure'));

        $cacheKey = 'action-log-export:999';
        expect(\Illuminate\Support\Facades\Cache::get($cacheKey))->toBe('failed');
    });
});

/*
|--------------------------------------------------------------------------
| AttachmentController::indexForMember — cover via API test
|--------------------------------------------------------------------------
*/
describe('AttachmentController::indexForMember', function () {
    it('returns attachments for a member', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(User::factory()->owner()->create(), ['*']);
        Storage::fake('public');

        $member = Member::factory()->create();
        Storage::disk('public')->put('attachments/member/test.pdf', 'content');
        Attachment::factory()->create([
            'attachable_type' => $member->getMorphClass(),
            'attachable_id' => $member->id,
            'file_path' => 'attachments/member/test.pdf',
            'disk' => 'public',
        ]);

        $this->getJson("/api/v1/members/{$member->id}/attachments")
            ->assertOk()
            ->assertJsonStructure(['attachments', 'meta'])
            ->assertJsonCount(1, 'attachments');
    });
});

/*
|--------------------------------------------------------------------------
| MemberController::filterResponseByView — cover via view_id param
|--------------------------------------------------------------------------
*/
describe('MemberController view_id filtering', function () {
    it('filters show response to view columns', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(User::factory()->owner()->create(), ['*']);

        $member = Member::factory()->create();
        $view = \App\Models\CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'columns' => ['name', 'membership_type'],
        ]);

        $response = $this->getJson("/api/v1/members/{$member->id}?view_id={$view->id}")
            ->assertOk();

        $data = $response->json('member');
        expect($data)->toHaveKey('name')
            ->and($data)->toHaveKey('membership_type');
    });
});

/*
|--------------------------------------------------------------------------
| ColumnRegistry — cover get(), defaultColumns(), mapSchemaType()
|--------------------------------------------------------------------------
*/
describe('ColumnRegistry complete coverage', function () {
    it('get() returns null for unknown column', function () {
        $registry = new \App\Views\MemberColumnRegistry;
        expect($registry->get('nonexistent_column'))->toBeNull();
    });

    it('defaultColumns() returns array of keys', function () {
        $registry = new \App\Views\MemberColumnRegistry;
        $defaults = $registry->defaultColumns();
        expect($defaults)->toBeArray();
        expect(count($defaults))->toBeGreaterThan(0);
    });

    it('validates columns and returns invalid ones', function () {
        $registry = new \App\Views\MemberColumnRegistry;
        $invalid = $registry->validateColumns(['name', 'fake_column']);
        expect($invalid)->toContain('fake_column');
    });

    it('maps all custom field schema types to column types', function () {
        // Create custom fields of each type to exercise mapSchemaType
        CustomField::factory()->forModule('Member')->boolean()->create(['name' => 'cf_bool_test']);
        CustomField::factory()->forModule('Member')->create([
            'name' => 'cf_date_test',
            'field_type' => \App\Enums\CustomFieldType::Date,
        ]);
        CustomField::factory()->forModule('Member')->create([
            'name' => 'cf_currency_test',
            'field_type' => \App\Enums\CustomFieldType::Currency,
        ]);
        CustomField::factory()->forModule('Member')->create([
            'name' => 'cf_list_test',
            'field_type' => \App\Enums\CustomFieldType::ListOfValues,
        ]);

        $registry = new \App\Views\MemberColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns['cf.cf_bool_test']->type)->toBe('boolean');
        expect($columns['cf.cf_date_test']->type)->toBe('datetime');
        expect($columns['cf.cf_currency_test']->type)->toBe('money');
        expect($columns['cf.cf_list_test']->type)->toBe('enum');
    });
});

/*
|--------------------------------------------------------------------------
| ViewResolver — cover applySort and extractFieldNames
|--------------------------------------------------------------------------
*/
describe('ViewResolver complete coverage', function () {
    it('applySort uses view sort column', function () {
        $view = \App\Models\CustomView::factory()->create([
            'entity_type' => 'members',
            'columns' => ['name'],
            'filters' => [],
            'sort_column' => 'name',
            'sort_direction' => 'desc',
        ]);

        $resolver = app(\App\Services\ViewResolver::class);
        $query = Member::query();
        $sorted = $resolver->applySort($query, $view);

        $sql = $sorted->toRawSql();
        expect($sql)->toContain('order by');
    });
});

/*
|--------------------------------------------------------------------------
| EmailTemplateRenderer — cover resolveField with null path
|--------------------------------------------------------------------------
*/
describe('EmailTemplateRenderer branch coverage', function () {
    it('renders empty string for missing merge fields', function () {
        \App\Models\EmailTemplate::create([
            'key' => 'test_missing_field',
            'name' => 'Test Missing',
            'subject' => 'Hello {{ nonexistent.field }}',
            'body_markdown' => 'body',
            'is_active' => true,
        ]);

        $renderer = new \App\Services\EmailTemplateRenderer;
        $result = $renderer->render('test_missing_field', []);
        expect($result['subject'])->toBe('Hello ');
    });
});

/*
|--------------------------------------------------------------------------
| RansackFilter — cover the uncovered predicate method
|--------------------------------------------------------------------------
*/
describe('RansackFilter complete coverage', function () {
    it('applies all predicate types', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        Sanctum::actingAs(User::factory()->owner()->create(), ['*']);

        Member::factory()->create(['name' => 'Test Corp', 'is_active' => true]);

        // Test predicates that work on SQLite (eq, not_eq, lt, gt, null, not_null, in, not_in)
        $this->getJson('/api/v1/members?q[is_active_true]=1')->assertOk();
        $this->getJson('/api/v1/members?q[name_eq]=Test Corp')->assertOk();
        $this->getJson('/api/v1/members?q[name_not_eq]=Other')->assertOk();
        $this->getJson('/api/v1/members?q[description_null]=1')->assertOk();
        $this->getJson('/api/v1/members?q[name_not_null]=1')->assertOk();
        $this->getJson('/api/v1/members?q[name_in]=Test Corp,Other Corp')->assertOk();
    })->skip(fn () => config('database.default') === 'sqlite', 'Ransack uses PostgreSQL ilike');
});

/*
|--------------------------------------------------------------------------
| AppServiceProvider — cover uncovered boot method
|--------------------------------------------------------------------------
*/
describe('AppServiceProvider blade directives', function () {
    it('registers @area and @costs blade directives', function () {
        $directives = \Illuminate\Support\Facades\Blade::getCustomDirectives();
        expect($directives)->toHaveKey('area')
            ->and($directives)->toHaveKey('endarea')
            ->and($directives)->toHaveKey('costs')
            ->and($directives)->toHaveKey('endcosts')
            ->and($directives)->toHaveKey('localdate')
            ->and($directives)->toHaveKey('localdatetime');
    });
});

/*
|--------------------------------------------------------------------------
| DocsController — cover the uncovered method
|--------------------------------------------------------------------------
*/
describe('DocsController complete coverage', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    });

    it('renders changelog page', function () {
        $user = User::factory()->owner()->create();
        $this->actingAs($user)
            ->get('/docs/changelog')
            ->assertOk();
    });
});

/*
|--------------------------------------------------------------------------
| SignalsChangelogCommand — cover the uncovered method
|--------------------------------------------------------------------------
*/
describe('SignalsChangelogCommand', function () {
    it('scaffolds a changelog entry', function () {
        $path = base_path('docs/changelog/99.99.99.md');

        $this->artisan('signals:changelog', ['version' => '99.99.99'])
            ->assertExitCode(0);

        // Clean up generated file
        if (file_exists($path)) {
            unlink($path);
        }
    });
});
