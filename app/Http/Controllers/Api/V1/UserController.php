<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Admin\DeactivateUser;
use App\Actions\Admin\InviteUser;
use App\Actions\Admin\UpdateUser;
use App\Data\Api\CreateUserData;
use App\Data\Api\UpdateUserData;
use App\Data\Api\UserData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'email',
        'is_admin',
        'is_active',
        'is_owner',
        'created_at',
        'last_login_at',
        'deactivated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'email',
        'created_at',
        'last_login_at',
    ];

    /**
     * List users with filtering, sorting, and pagination.
     */
    #[ApiResponse(200, 'Paginated user list', type: 'array{users: list<array{id: int, name: string, email: string, is_admin: bool, is_owner: bool, is_active: bool, email_verified_at: string|null, invited_at: string|null, invitation_accepted_at: string|null, last_login_at: string|null, deactivated_at: string|null, created_at: string, updated_at: string, roles: list<string>}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('users.view', 'users:read');

        $query = User::query()->with('roles');
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $users = $paginator->getCollection()->map(
            fn (User $user): array => UserData::fromModel($user)->toArray()
        )->all();

        return $this->respondWithCollection($users, 'users', $paginator);
    }

    /**
     * Show a single user.
     */
    #[ApiResponse(200, 'User details', type: 'array{user: array{id: int, name: string, email: string, is_admin: bool, is_owner: bool, is_active: bool, email_verified_at: string|null, invited_at: string|null, invitation_accepted_at: string|null, last_login_at: string|null, deactivated_at: string|null, created_at: string, updated_at: string, roles: list<string>}}')]
    public function show(User $user): JsonResponse
    {
        $this->authorizeApi('users.view', 'users:read');

        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
        );
    }

    /**
     * Create (invite) a new user.
     */
    #[ApiResponse(201, 'User created', type: 'array{user: array{id: int, name: string, email: string, is_admin: bool, is_owner: bool, is_active: bool, email_verified_at: string|null, invited_at: string|null, invitation_accepted_at: string|null, last_login_at: string|null, deactivated_at: string|null, created_at: string, updated_at: string, roles: list<string>}}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('users.invite', 'users:write');

        $validated = $request->validate(CreateUserData::rules());
        $dto = CreateUserData::from($validated);

        $user = (new InviteUser)($dto->toInviteUserData());
        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update an existing user.
     */
    #[ApiResponse(200, 'User updated', type: 'array{user: array{id: int, name: string, email: string, is_admin: bool, is_owner: bool, is_active: bool, email_verified_at: string|null, invited_at: string|null, invitation_accepted_at: string|null, last_login_at: string|null, deactivated_at: string|null, created_at: string, updated_at: string, roles: list<string>}}')]
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeApi('users.edit', 'users:write');

        $validated = $request->validate(UpdateUserData::rules($user->id));
        $dto = UpdateUserData::from($validated);

        $user = (new UpdateUser)($user, $dto->toActionData());
        $user->load('roles');

        return $this->respondWith(
            UserData::fromModel($user)->toArray(),
            'user',
        );
    }

    /**
     * Deactivate a user.
     */
    #[ApiResponse(204, 'User deactivated')]
    public function destroy(User $user): JsonResponse
    {
        $this->authorizeApi('users.edit', 'users:write');

        (new DeactivateUser)($user);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
