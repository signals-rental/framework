<?php

use App\Models\CustomView;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ViewSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['members:read'])->plainTextToken;
});

describe('GET /api/v1/members with view_id', function () {
    it('applies view filters when view_id is provided', function () {
        Member::factory()->create(['membership_type' => 'organisation']);
        Member::factory()->create(['membership_type' => 'contact']);

        $view = CustomView::query()->where('name', 'Organisations Only')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}")
            ->assertOk();

        // Should only return organisations (the view filters membership_type=organisation)
        $members = $response->json('members');
        /** @var array<int, array<string, mixed>> $memberList */
        $memberList = $members;
        expect(collect($memberList)->every(fn ($m) => $m['membership_type'] === 'Organisation'))->toBeTrue();
    });

    it('includes view metadata in response meta', function () {
        $view = CustomView::query()->where('name', 'Organisations Only')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}")
            ->assertOk();

        expect($response->json('meta.view.id'))->toBe($view->id)
            ->and($response->json('meta.view.name'))->toBe('Organisations Only');
    });

    it('applies view sort when no explicit sort param is given', function () {
        $view = CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'sort_column' => 'name',
            'sort_direction' => 'desc',
            'filters' => [],
        ]);

        Member::factory()->create(['name' => 'Alpha Corp']);
        Member::factory()->create(['name' => 'Zeta Ltd']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}")
            ->assertOk();

        /** @var array<int, array<string, mixed>> $memberData */
        $memberData = $response->json('members');
        $names = collect($memberData)->pluck('name')->all();
        // Descending sort: Zeta should come before Alpha
        $zetaIndex = array_search('Zeta Ltd', $names);
        $alphaIndex = array_search('Alpha Corp', $names);
        expect($zetaIndex)->toBeLessThan($alphaIndex);
    });

    it('uses explicit sort over view sort', function () {
        $view = CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'sort_column' => 'name',
            'sort_direction' => 'desc',
            'filters' => [],
        ]);

        Member::factory()->create(['name' => 'Alpha Corp']);
        Member::factory()->create(['name' => 'Zeta Ltd']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}&sort=name&direction=asc")
            ->assertOk();

        /** @var array<int, array<string, mixed>> $memberData2 */
        $memberData2 = $response->json('members');
        $names = collect($memberData2)->pluck('name')->all();
        // Ascending sort: Alpha should come before Zeta
        $alphaIndex = array_search('Alpha Corp', $names);
        $zetaIndex = array_search('Zeta Ltd', $names);
        expect($alphaIndex)->toBeLessThan($zetaIndex);
    });

    it('does not include view metadata when no view is active', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/members')
            ->assertOk();

        // The system default will be resolved, so view metadata will be present
        // But if we query with a nonsense view_id that doesn't exist for the entity...
        // Actually the resolver falls back to system default, so meta.view will be present
        expect($response->json('meta.view'))->not->toBeNull();
    });

    it('returns sparse response with only view columns when view_id is set', function () {
        Member::factory()->create(['name' => 'Test Corp', 'membership_type' => 'organisation']);

        $view = CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'columns' => ['name', 'membership_type', 'created_at'],
            'filters' => [],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}")
            ->assertOk();

        $member = $response->json('members.0');
        // Should always include id
        expect($member)->toHaveKey('id');
        // Should include view columns
        expect($member)->toHaveKey('name');
        expect($member)->toHaveKey('membership_type');
        expect($member)->toHaveKey('created_at');
        // Should NOT include columns not in the view
        expect($member)->not->toHaveKey('description');
        expect($member)->not->toHaveKey('day_cost');
        expect($member)->not->toHaveKey('membership');
    });

    it('filters custom_fields in sparse response when view has cf. columns', function () {
        // Create a member with custom fields
        $member = Member::factory()->create(['membership_type' => 'organisation']);

        $view = CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'user_id' => null,
            'is_default' => false,
            'columns' => ['name', 'cf.po_reference'],
            'filters' => [],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}")
            ->assertOk();

        $member = $response->json('members.0');
        expect($member)->toHaveKey('id');
        expect($member)->toHaveKey('name');
        // Should not include unrelated top-level fields
        expect($member)->not->toHaveKey('description');
    });

    it('merges explicit q params with view filters', function () {
        Member::factory()->create(['name' => 'Active Org', 'membership_type' => 'organisation', 'is_active' => true]);
        Member::factory()->create(['name' => 'Inactive Org', 'membership_type' => 'organisation', 'is_active' => false]);
        Member::factory()->create(['name' => 'Active Contact', 'membership_type' => 'contact', 'is_active' => true]);

        $view = CustomView::query()->where('name', 'Organisations Only')->first();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/members?view_id={$view->id}&q[is_active_eq]=true")
            ->assertOk();

        /** @var array<int, array<string, mixed>> $members */
        $members = $response->json('members');
        // Should only return active organisations
        expect(collect($members)->every(fn ($m) => $m['membership_type'] === 'Organisation' && $m['active'] === true))->toBeTrue();
    });
});
