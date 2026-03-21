<?php

namespace App\Policies\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for policies that delegate all operations to permission checks.
 *
 * Subclasses define `viewPermission()` and `managePermission()` to control
 * which Spatie permission is checked for read vs write operations.
 * For single-permission policies, override only `managePermission()` and
 * `viewPermission()` will fall back to it.
 */
trait AuthorizesByPermission
{
    /**
     * The permission required for view operations (viewAny, view).
     * Defaults to managePermission() if not overridden.
     */
    protected function viewPermission(): string
    {
        return $this->managePermission();
    }

    /**
     * The permission required for write operations (create, update, delete).
     */
    abstract protected function managePermission(): string;

    /**
     * The permission required for create operations.
     * Defaults to managePermission() if not overridden.
     */
    protected function createPermission(): string
    {
        return $this->managePermission();
    }

    /**
     * The permission required for update operations.
     * Defaults to managePermission() if not overridden.
     */
    protected function editPermission(): string
    {
        return $this->managePermission();
    }

    /**
     * The permission required for delete operations.
     * Defaults to managePermission() if not overridden.
     */
    protected function deletePermission(): string
    {
        return $this->managePermission();
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission());
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $user->can($this->viewPermission());
    }

    public function create(User $user): bool
    {
        return $user->can($this->createPermission());
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $user->can($this->editPermission());
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $user->can($this->deletePermission());
    }
}
