<?php

use App\Models\CustomView;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ViewSeeder::class);
    $this->user = User::factory()->owner()->create();
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/custom_views', function () {
    it('lists views for an entity type', function () {
        $this->getJson('/api/v1/custom_views?entity_type=members')
            ->assertOk()
            ->assertJsonStructure([
                'custom_views' => [
                    '*' => ['id', 'name', 'entity_type', 'visibility', 'columns', 'filters'],
                ],
                'meta',
            ])
            ->assertJsonCount(5, 'custom_views');
    });

    it('includes personal views for current user', function () {
        CustomView::factory()->create([
            'entity_type' => 'members',
            'user_id' => $this->user->id,
            'name' => 'My Personal View',
        ]);

        $response = $this->getJson('/api/v1/custom_views?entity_type=members');
        $response->assertOk()
            ->assertJsonCount(6, 'custom_views');

        /** @var array<int, array<string, mixed>> $views */
        $views = $response->json('custom_views');
        $names = collect($views)->pluck('name')->all();
        expect($names)->toContain('My Personal View');
    });

    it('excludes other users personal views', function () {
        $otherUser = User::factory()->create();
        CustomView::factory()->create([
            'entity_type' => 'members',
            'user_id' => $otherUser->id,
            'name' => 'Other Users View',
        ]);

        $response = $this->getJson('/api/v1/custom_views?entity_type=members');
        /** @var array<int, array<string, mixed>> $viewData */
        $viewData = $response->json('custom_views');
        $names = collect($viewData)->pluck('name')->all();

        expect($names)->not->toContain('Other Users View');
    });

    it('returns meta with total count', function () {
        $this->getJson('/api/v1/custom_views?entity_type=members')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    });
});

describe('GET /api/v1/custom_views/{id}', function () {
    it('shows a single view', function () {
        $view = CustomView::first();

        $this->getJson("/api/v1/custom_views/{$view->id}")
            ->assertOk()
            ->assertJsonPath('custom_view.id', $view->id)
            ->assertJsonPath('custom_view.name', $view->name)
            ->assertJsonPath('custom_view.entity_type', $view->entity_type)
            ->assertJsonPath('custom_view.visibility', $view->visibility);
    });

    it('returns 404 for non-existent view', function () {
        $this->getJson('/api/v1/custom_views/99999')
            ->assertNotFound();
    });

    it('includes all expected fields', function () {
        $view = CustomView::first();

        $this->getJson("/api/v1/custom_views/{$view->id}")
            ->assertOk()
            ->assertJsonStructure([
                'custom_view' => [
                    'id',
                    'name',
                    'entity_type',
                    'visibility',
                    'user_id',
                    'is_default',
                    'columns',
                    'filters',
                    'sort_column',
                    'sort_direction',
                    'per_page',
                    'config',
                    'created_at',
                    'updated_at',
                ],
            ]);
    });
});

describe('POST /api/v1/custom_views', function () {
    it('creates a personal view', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'My Custom View',
            'entity_type' => 'members',
            'visibility' => 'personal',
            'columns' => ['name', 'email', 'phone'],
            'sort_column' => 'name',
            'sort_direction' => 'asc',
            'per_page' => 25,
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.name', 'My Custom View')
            ->assertJsonPath('custom_view.visibility', 'personal')
            ->assertJsonPath('custom_view.user_id', $this->user->id)
            ->assertJsonPath('custom_view.columns', ['name', 'email', 'phone'])
            ->assertJsonPath('custom_view.per_page', 25);
    });

    it('defaults visibility to personal', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Default Visibility',
            'entity_type' => 'members',
            'columns' => ['name'],
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.visibility', 'personal');
    });

    it('validates required fields on create', function () {
        $this->postJson('/api/v1/custom_views', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'entity_type', 'columns']);
    });

    it('validates columns must be a non-empty array', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Test',
            'entity_type' => 'members',
            'columns' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns']);
    });

    it('validates sort_direction must be asc or desc', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Test',
            'entity_type' => 'members',
            'columns' => ['name'],
            'sort_direction' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort_direction']);
    });

    it('validates per_page range', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Test',
            'entity_type' => 'members',
            'columns' => ['name'],
            'per_page' => 200,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    });

    it('validates visibility must be personal or shared', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Test',
            'entity_type' => 'members',
            'columns' => ['name'],
            'visibility' => 'system',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visibility']);
    });

    it('rejects invalid column keys on create', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Bad Columns',
            'entity_type' => 'members',
            'columns' => ['name', 'nonexistent_column', 'also_invalid'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns']);
    });

    it('accepts valid column keys on create', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Good Columns',
            'entity_type' => 'members',
            'columns' => ['name', 'email', 'phone', 'is_active'],
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.columns', ['name', 'email', 'phone', 'is_active']);
    });

    it('creates view with filters', function () {
        $filters = [
            ['field' => 'membership_type', 'predicate' => 'eq', 'value' => 'contact'],
        ];

        $this->postJson('/api/v1/custom_views', [
            'name' => 'Contacts Filter',
            'entity_type' => 'members',
            'columns' => ['name', 'email'],
            'filters' => $filters,
        ])
            ->assertCreated()
            ->assertJsonPath('custom_view.filters.0.field', 'membership_type');
    });

    it('persists view in database', function () {
        $this->postJson('/api/v1/custom_views', [
            'name' => 'Persisted View',
            'entity_type' => 'members',
            'columns' => ['name'],
        ])->assertCreated();

        expect(CustomView::where('name', 'Persisted View')->exists())->toBeTrue();
    });
});

describe('PUT /api/v1/custom_views/{id}', function () {
    it('updates a personal view', function () {
        $view = CustomView::factory()->create([
            'user_id' => $this->user->id,
            'entity_type' => 'members',
            'name' => 'Original Name',
        ]);

        $this->putJson("/api/v1/custom_views/{$view->id}", [
            'name' => 'Updated Name',
            'columns' => ['name', 'email'],
        ])
            ->assertOk()
            ->assertJsonPath('custom_view.name', 'Updated Name')
            ->assertJsonPath('custom_view.columns', ['name', 'email']);
    });

    it('partially updates a view', function () {
        $view = CustomView::factory()->create([
            'user_id' => $this->user->id,
            'entity_type' => 'members',
            'name' => 'Original Name',
            'per_page' => 20,
        ]);

        $this->putJson("/api/v1/custom_views/{$view->id}", [
            'name' => 'New Name',
        ])
            ->assertOk()
            ->assertJsonPath('custom_view.name', 'New Name')
            ->assertJsonPath('custom_view.per_page', 20);
    });

    it('prevents other users from updating personal views', function () {
        // Use a non-owner user so Gate checks are not bypassed
        $nonOwner = User::factory()->create();
        $nonOwner->assignRole('Admin');
        Sanctum::actingAs($nonOwner, ['*']);

        $otherUser = User::factory()->create();
        $view = CustomView::factory()->create([
            'user_id' => $otherUser->id,
            'entity_type' => 'members',
        ]);

        $this->putJson("/api/v1/custom_views/{$view->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    });

    it('rejects invalid column keys on update', function () {
        $view = CustomView::factory()->create([
            'user_id' => $this->user->id,
            'entity_type' => 'members',
        ]);

        $this->putJson("/api/v1/custom_views/{$view->id}", [
            'columns' => ['name', 'fake_column'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns']);
    });

    it('returns 404 for non-existent view', function () {
        $this->putJson('/api/v1/custom_views/99999', [
            'name' => 'Test',
        ])->assertNotFound();
    });
});

describe('DELETE /api/v1/custom_views/{id}', function () {
    it('deletes a personal view', function () {
        $view = CustomView::factory()->create([
            'user_id' => $this->user->id,
            'entity_type' => 'members',
        ]);

        $this->deleteJson("/api/v1/custom_views/{$view->id}")
            ->assertNoContent();

        expect(CustomView::find($view->id))->toBeNull();
    });

    it('prevents deleting system views', function () {
        $view = CustomView::where('visibility', 'system')->first();

        $this->deleteJson("/api/v1/custom_views/{$view->id}")
            ->assertUnprocessable();

        expect(CustomView::find($view->id))->not->toBeNull();
    });

    it('prevents other users from deleting personal views', function () {
        // Use a non-owner user so Gate checks are not bypassed
        $nonOwner = User::factory()->create();
        $nonOwner->assignRole('Admin');
        Sanctum::actingAs($nonOwner, ['*']);

        $otherUser = User::factory()->create();
        $view = CustomView::factory()->create([
            'user_id' => $otherUser->id,
            'entity_type' => 'members',
        ]);

        $this->deleteJson("/api/v1/custom_views/{$view->id}")
            ->assertForbidden();
    });

    it('returns 404 for non-existent view', function () {
        $this->deleteJson('/api/v1/custom_views/99999')
            ->assertNotFound();
    });
});

describe('POST /api/v1/custom_views/{id}/clone', function () {
    it('clones a view as personal copy', function () {
        $source = CustomView::where('visibility', 'system')->first();

        $response = $this->postJson("/api/v1/custom_views/{$source->id}/clone");

        $response->assertCreated()
            ->assertJsonPath('custom_view.visibility', 'personal')
            ->assertJsonPath('custom_view.user_id', $this->user->id)
            ->assertJsonPath('custom_view.is_default', false);

        expect($response->json('custom_view.name'))->toContain('(Copy)');
    });

    it('preserves source view columns and filters in clone', function () {
        $source = CustomView::where('name', 'Organisations Only')->first();

        $response = $this->postJson("/api/v1/custom_views/{$source->id}/clone");

        $response->assertCreated()
            ->assertJsonPath('custom_view.columns', $source->columns)
            ->assertJsonPath('custom_view.sort_column', $source->sort_column)
            ->assertJsonPath('custom_view.sort_direction', $source->sort_direction)
            ->assertJsonPath('custom_view.per_page', $source->per_page);

        expect($response->json('custom_view.filters'))->toBe($source->filters);
    });

    it('preserves source entity type in clone', function () {
        $source = CustomView::where('visibility', 'system')->first();

        $this->postJson("/api/v1/custom_views/{$source->id}/clone")
            ->assertCreated()
            ->assertJsonPath('custom_view.entity_type', $source->entity_type);
    });

    it('creates a separate database record for clone', function () {
        $countBefore = CustomView::count();
        $source = CustomView::where('visibility', 'system')->first();

        $this->postJson("/api/v1/custom_views/{$source->id}/clone")
            ->assertCreated();

        expect(CustomView::count())->toBe($countBefore + 1);
    });
});

describe('authentication and authorization', function () {
    it('requires authentication for all endpoints', function () {
        // Reset to unauthenticated state
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/custom_views')->assertUnauthorized();
        $this->getJson('/api/v1/custom_views/1')->assertUnauthorized();
        $this->postJson('/api/v1/custom_views', [])->assertUnauthorized();
        $this->putJson('/api/v1/custom_views/1', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/custom_views/1')->assertUnauthorized();
    });
});
