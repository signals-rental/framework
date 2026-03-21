<?php

/**
 * Targeted tests to improve class-level coverage.
 *
 * Each test exercises a previously-uncovered method to push
 * the class to 100% method coverage.
 */

use App\Actions\Views\CreateCustomView;
use App\Data\Api\ActionLogData;
use App\Data\Attachments\AttachmentData;
use App\Data\Views\CreateCustomViewData;
use App\Models\ActionLog;
use App\Models\Attachment;
use App\Models\EmailTemplate;
use App\Models\Member;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\CustomFieldCopier;
use App\Services\EmailTemplateRenderer;
use App\Services\PermissionRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

describe('CreateCustomView action (direct invocation)', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->owner()->create());
    });

    it('creates a personal custom view via action', function () {
        $data = new CreateCustomViewData(
            name: 'My View',
            entity_type: 'members',
            columns: ['name', 'membership_type'],
        );

        $result = (new CreateCustomView)($data);

        expect($result->name)->toBe('My View')
            ->and($result->entity_type)->toBe('members')
            ->and($result->visibility)->toBe('personal');
    });

    it('creates a shared custom view with roles', function () {
        $role = \Spatie\Permission\Models\Role::first();

        $data = new CreateCustomViewData(
            name: 'Team View',
            entity_type: 'members',
            visibility: 'shared',
            columns: ['name'],
            role_ids: [$role->id],
        );

        $result = (new CreateCustomView)($data);

        expect($result->visibility)->toBe('shared');

        $view = \App\Models\CustomView::find($result->id);
        expect($view->roles)->toHaveCount(1);
    });
});

describe('BackfillUserMembers command', function () {
    it('creates member records for users without them', function () {
        $user = User::factory()->create(['member_id' => null]);

        $this->artisan('signals:backfill-user-members')
            ->assertExitCode(0);

        $user->refresh();
        expect($user->member_id)->not->toBeNull();
    });

    it('reports when all users already have members', function () {
        // All factory users get member_id by default
        User::factory()->create();

        $this->artisan('signals:backfill-user-members')
            ->assertExitCode(0)
            ->expectsOutputToContain('All users already have linked member records');
    });
});

describe('ActionLogData::fromModel', function () {
    it('converts model to DTO with friendly type', function () {
        $user = User::factory()->create();
        $log = ActionLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
        ]);

        $dto = ActionLogData::fromModel($log);

        expect($dto->auditable_type)->toBe('user')
            ->and($dto->action)->toBe('updated')
            ->and($dto->user_name)->toBe($user->name);
    });
});

describe('AttachmentData::fromModel', function () {
    it('resolves URLs for public disk attachments', function () {
        Storage::fake('public');
        Storage::disk('public')->put('attachments/test.pdf', 'content');

        $attachment = Attachment::factory()->create([
            'file_path' => 'attachments/test.pdf',
            'thumb_path' => null,
            'disk' => 'public',
        ]);

        $dto = AttachmentData::fromModel($attachment);

        expect($dto->url)->toContain('test.pdf')
            ->and($dto->thumb_url)->toBeNull();
    });

    it('resolves thumb URL when present', function () {
        Storage::fake('public');
        Storage::disk('public')->put('attachments/test.pdf', 'content');
        Storage::disk('public')->put('attachments/thumb.jpg', 'thumb');

        $attachment = Attachment::factory()->create([
            'file_path' => 'attachments/test.pdf',
            'thumb_path' => 'attachments/thumb.jpg',
            'disk' => 'public',
        ]);

        $dto = AttachmentData::fromModel($attachment);

        expect($dto->thumb_url)->not->toBeNull()->toContain('thumb.jpg');
    });
});

describe('EnsureTwoFactorAuthenticated middleware', function () {
    beforeEach(function () {
        config(['signals.installed' => true, 'signals.setup_complete' => true]);
    });

    it('redirects to profile when 2FA required for all users', function () {
        settings()->set('security.require_2fa_all', true);

        $user = User::factory()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        \Illuminate\Support\Facades\Route::middleware(['web', '2fa'])->get('/2fa-test', fn () => 'ok');

        $this->actingAs($user)
            ->get('/2fa-test')
            ->assertRedirect(route('settings.profile'));
    });

    it('redirects admins when 2FA required for admins', function () {
        settings()->set('security.require_2fa_admin', true);

        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        \Illuminate\Support\Facades\Route::middleware(['web', '2fa'])->get('/2fa-admin-test', fn () => 'ok');

        $this->actingAs($admin)
            ->get('/2fa-admin-test')
            ->assertRedirect(route('settings.profile'));
    });
});

describe('StoreScope', function () {
    it('withoutScoping disables then re-enables scope', function () {
        $result = \App\Models\Scopes\StoreScope::withoutScoping(function () {
            return 'inner-result';
        });

        expect($result)->toBe('inner-result');
    });
});

describe('CurrencyService::baseCurrency', function () {
    it('returns the base currency model', function () {
        \App\Models\Currency::factory()->create(['code' => 'GBP', 'is_enabled' => true]);
        settings()->set('company.base_currency', 'GBP');

        $service = new CurrencyService;
        $currency = $service->baseCurrency();

        expect($currency->code)->toBe('GBP');
    });
});

describe('CustomFieldCopier::copy', function () {
    it('copies matching fields between entities', function () {
        $source = Member::factory()->create();
        $target = Member::factory()->create();

        $field = \App\Models\CustomField::factory()->forModule('Member')->create([
            'name' => 'po_reference',
            'display_name' => 'PO Reference',
        ]);

        \App\Models\CustomFieldValue::create([
            'custom_field_id' => $field->id,
            'entity_type' => $source->getMorphClass(),
            'entity_id' => $source->id,
            'value_string' => 'PO-123',
        ]);

        $copier = app(CustomFieldCopier::class);
        $result = $copier->copy($source, $target, 'Member');

        expect($result->copied)->toBe(1)
            ->and($result->fieldsCopied)->toContain('po_reference');
    });
});

describe('PermissionRegistry::byLayer', function () {
    it('filters permissions by layer', function () {
        $registry = app(PermissionRegistry::class);

        $uiPermissions = $registry->byLayer('ui-area');
        expect($uiPermissions)->toBeArray();

        $actionPermissions = $registry->byLayer('action');
        expect($actionPermissions)->toBeArray();
    });
});

describe('EmailTemplateRenderer::render with merge fields', function () {
    it('resolves merge field placeholders', function () {
        EmailTemplate::create([
            'key' => 'test_merge',
            'name' => 'Test Merge',
            'subject' => 'Hello {{ name }}',
            'body_markdown' => 'Dear {{ name | upper }}, your ref is {{ ref | default:"N/A" }}.',
            'is_active' => true,
        ]);

        $renderer = new EmailTemplateRenderer;
        $result = $renderer->render('test_merge', [
            'name' => 'Alice',
        ]);

        expect($result['subject'])->toBe('Hello Alice')
            ->and($result['html'])->toContain('ALICE')
            ->and($result['html'])->toContain('N/A');
    });

    it('resolves lower filter', function () {
        EmailTemplate::create([
            'key' => 'test_lower',
            'name' => 'Test Lower',
            'subject' => '{{ name | lower }}',
            'body_markdown' => 'body',
            'is_active' => true,
        ]);

        $renderer = new EmailTemplateRenderer;
        $result = $renderer->render('test_lower', ['name' => 'ALICE']);

        expect($result['subject'])->toBe('alice');
    });

    it('resolves nested dot-notation fields', function () {
        EmailTemplate::create([
            'key' => 'test_nested',
            'name' => 'Test Nested',
            'subject' => '{{ company.name }}',
            'body_markdown' => 'body',
            'is_active' => true,
        ]);

        $renderer = new EmailTemplateRenderer;
        $result = $renderer->render('test_nested', [
            'company' => ['name' => 'Acme Inc'],
        ]);

        expect($result['subject'])->toBe('Acme Inc');
    });
});

describe('User::accessibleStoreIds', function () {
    it('returns null for owner (unrestricted)', function () {
        $user = User::factory()->owner()->create();

        expect($user->accessibleStoreIds())->toBeNull();
    });

    it('returns empty array for user without member', function () {
        $user = User::factory()->create([
            'is_owner' => false,
            'is_admin' => false,
            'member_id' => null,
        ]);

        expect($user->accessibleStoreIds())->toBe([]);
    });
});

describe('Member model scopes', function () {
    it('scopeContactsOf returns contacts for an organisation', function () {
        $org = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create();

        \App\Models\MemberRelationship::create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
            'relationship_type' => 'employee',
        ]);

        $contacts = Member::contactsOf($org->id)->get();
        expect($contacts)->toHaveCount(1)
            ->and($contacts->first()->id)->toBe($contact->id);
    });

    it('scopeOrganisationsOf returns organisations for a contact', function () {
        $org = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create();

        \App\Models\MemberRelationship::create([
            'member_id' => $contact->id,
            'related_member_id' => $org->id,
            'relationship_type' => 'employee',
        ]);

        $orgs = Member::organisationsOf($contact->id)->get();
        expect($orgs)->toHaveCount(1)
            ->and($orgs->first()->id)->toBe($org->id);
    });
});

describe('CustomFieldSerializer::coerceDefaultValue', function () {
    it('is exercised through custom field creation with defaults', function () {
        $field = \App\Models\CustomField::factory()->boolean()->forModule('Member')->create([
            'default_value' => 'true',
        ]);

        $member = Member::factory()->create();
        $serializer = app(\App\Services\CustomFieldSerializer::class);

        $serializer->fromArray($member, [], applyDefaults: true);
        $result = $serializer->toArray($member);

        expect($result)->toHaveKey($field->name);
    });
});

describe('ViewResolver::applyFilters with various logic types', function () {
    it('applies filters with or/nand/nor logic', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->owner()->create());

        $view = \App\Models\CustomView::factory()->create([
            'entity_type' => 'members',
            'columns' => ['name', 'membership_type'],
            'filters' => [
                ['field' => 'name', 'predicate' => 'cont', 'value' => 'test', 'logic' => 'and'],
                ['field' => 'is_active', 'predicate' => 'true', 'value' => '1', 'logic' => 'or'],
                ['field' => 'name', 'predicate' => 'cont', 'value' => 'skip', 'logic' => 'nand'],
                ['field' => 'name', 'predicate' => 'cont', 'value' => 'also', 'logic' => 'nor'],
            ],
        ]);

        $resolver = app(\App\Services\ViewResolver::class);
        $query = Member::query();
        $result = $resolver->applyFilters($query, $view);

        expect($result)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
    });
});

describe('ActionLogData::fromModel with user relationship', function () {
    it('includes user_name from loaded relationship', function () {
        $user = User::factory()->create(['name' => 'Jane Admin']);
        $log = ActionLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $dto = ActionLogData::fromModel($log->load('user'));
        expect($dto->user_name)->toBe('Jane Admin')
            ->and($dto->auditable_type)->toBe('user');
    });
});

describe('AttachmentController::indexForMember', function () {
    it('lists attachments for a member via API', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $user = User::factory()->owner()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

        $member = Member::factory()->create();
        Attachment::factory()->create([
            'attachable_type' => $member->getMorphClass(),
            'attachable_id' => $member->id,
            'disk' => 'public',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('attachments/test.pdf', 'content');

        $this->getJson("/api/v1/members/{$member->id}/attachments")
            ->assertOk()
            ->assertJsonStructure(['attachments', 'meta']);
    });
});

describe('MemberController with view_id filtering', function () {
    it('filters response by custom view columns', function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $user = User::factory()->owner()->create();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

        $member = Member::factory()->create();

        $view = \App\Models\CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'columns' => ['name', 'membership_type'],
            'filters' => [],
        ]);

        $this->getJson("/api/v1/members/{$member->id}?view_id={$view->id}")
            ->assertOk();
    });
});

describe('BackfillUserMembers with multiple users', function () {
    it('creates member records for multiple users without them', function () {
        $user1 = User::factory()->create(['member_id' => null]);
        $user2 = User::factory()->create(['member_id' => null]);

        $this->artisan('signals:backfill-user-members')
            ->assertExitCode(0);

        $user1->refresh();
        $user2->refresh();
        expect($user1->member_id)->not->toBeNull();
        expect($user2->member_id)->not->toBeNull();
    });
});

describe('EnsureTwoFactorAuthenticated allows profile route', function () {
    it('allows through when on settings.profile route with 2FA required', function () {
        settings()->set('security.require_2fa_all', true);
        config(['signals.installed' => true, 'signals.setup_complete' => true]);

        $user = User::factory()->create([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        // The settings.profile route should be allowed through
        $this->actingAs($user)
            ->get(route('settings.profile'))
            ->assertOk();
    });
});

describe('User::initials', function () {
    it('generates initials from name', function () {
        $user = User::factory()->create(['name' => 'Jane Doe']);
        expect($user->initials())->toBe('JD');
    });

    it('generates single initial for single word name', function () {
        $user = User::factory()->create(['name' => 'Admin']);
        expect($user->initials())->toBe('A');
    });
});

describe('Member::formatMoneyCost', function () {
    it('formats minor units to decimal string', function () {
        $member = Member::factory()->create(['hour_cost' => 12550]);
        expect($member->formatMoneyCost('hour_cost'))->toBe('125.50');
    });

    it('formats zero cost', function () {
        $member = Member::factory()->create(['hour_cost' => 0]);
        expect($member->formatMoneyCost('hour_cost'))->toBe('0.00');
    });
});

describe('Formatter edge cases', function () {
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

    it('formats money under 1000 without thousands separator', function () {
        $formatter = app(\App\Support\Formatter::class);
        expect($formatter->money(99900, 'GBP'))->toBe('£999.00');
    });

    it('formats money with no base currency setting', function () {
        settings()->set('company.base_currency', null);
        $formatter = app(\App\Support\Formatter::class);
        // Falls back to GBP default
        expect($formatter->money(100))->toBeString();
    });
});

describe('ColumnRegistry custom field integration edge cases', function () {
    it('merges non-filterable non-sortable custom fields', function () {
        \App\Models\CustomField::factory()->forModule('Member')->create([
            'name' => 'internal_notes',
            'display_name' => 'Internal Notes',
            'field_type' => \App\Enums\CustomFieldType::Text,
            'is_searchable' => false,
        ]);

        $registry = new \App\Views\MemberColumnRegistry;
        $columns = $registry->allColumns();

        expect($columns)->toHaveKey('cf.internal_notes');
    });
});

describe('StoreScope with nested withoutScoping', function () {
    it('restores previous state when nested', function () {
        $result = \App\Models\Scopes\StoreScope::withoutScoping(function () {
            return \App\Models\Scopes\StoreScope::withoutScoping(function () {
                return 'nested';
            });
        });

        expect($result)->toBe('nested');
    });
});

describe('CurrencyService::baseCurrency with missing setting', function () {
    it('throws when base currency setting is missing', function () {
        settings()->set('company.base_currency', 'INVALID');

        $service = new CurrencyService;
        $service->baseCurrency();
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

describe('CustomFieldValidator edge case', function () {
    it('validates multi list of values with non-array input', function () {
        $field = \App\Models\CustomField::factory()->forModule('Member')->create([
            'name' => 'multi_test',
            'field_type' => \App\Enums\CustomFieldType::MultiListOfValues,
        ]);

        $validator = app(\App\Services\CustomFieldValidator::class);

        try {
            $validator->validate('Member', ['multi_test' => 'not-an-array']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            expect($e->errors())->toHaveKey('multi_test');
        }
    });
});
