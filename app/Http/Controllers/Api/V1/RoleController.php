<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\CreateRole;
use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\UpdateRole;
use App\Data\Api\CreateRoleData;
use App\Data\Api\RoleData;
use App\Data\Api\UpdateRoleData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_system',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
        'created_at',
    ];

    /**
     * List all roles with their permissions.
     *
     * @operationId listRoles
     */
    #[ApiResponse(200, 'All roles', type: 'array{roles: list<array{id: int, name: string, description: string|null, is_system: bool, sort_order: int, permissions: list<string>, created_at: string|null, updated_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:read');

        $query = Role::query()->with('permissions');
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->orderBy('sort_order');
        }

        $paginator = $this->paginateQuery($query, $request);

        $roles = $paginator->getCollection()->map(
            fn (Role $role): array => RoleData::fromModel($role)->toArray()
        )->all();

        return $this->respondWithCollection($roles, 'roles', $paginator);
    }

    /**
     * Show a single role with its permissions.
     *
     * @operationId getRole
     */
    #[ApiResponse(200, 'Role details', type: 'array{role: array{id: int, name: string, description: string|null, is_system: bool, sort_order: int, permissions: list<string>, created_at: string|null, updated_at: string|null}}')]
    public function show(Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:read');

        $role->load('permissions');

        return $this->respondWith(RoleData::fromModel($role)->toArray(), 'role');
    }

    /**
     * Create a new role.
     *
     * @operationId createRole
     */
    #[ApiResponse(201, 'Role created', type: 'array{role: array{id: int, name: string, description: string|null, is_system: bool, sort_order: int, permissions: list<string>, created_at: string|null, updated_at: string|null}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        $validated = $request->validate(CreateRoleData::rules());
        $dto = CreateRoleData::from($validated);

        $role = (new CreateRole)($dto->toActionData());
        $role->load('permissions');

        return $this->respondWith(
            RoleData::fromModel($role)->toArray(),
            'role',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing role.
     *
     * @operationId updateRole
     */
    #[ApiResponse(200, 'Role updated', type: 'array{role: array{id: int, name: string, description: string|null, is_system: bool, sort_order: int, permissions: list<string>, created_at: string|null, updated_at: string|null}}')]
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        $validated = $request->validate(UpdateRoleData::rules($role->id));
        $dto = UpdateRoleData::from($validated);

        $role = (new UpdateRole)($role, $dto->toActionData());
        $role->load('permissions');

        return $this->respondWith(RoleData::fromModel($role)->toArray(), 'role');
    }

    /**
     * Delete a role.
     *
     * @operationId deleteRole
     */
    #[ApiResponse(204, 'Role deleted')]
    public function destroy(Role $role): JsonResponse
    {
        $this->authorizeApi('roles.manage', 'roles:write');

        (new DeleteRole)($role);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
