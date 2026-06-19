<?php

namespace App\Guards\Opportunities\Stages;

use App\Guards\Opportunities\Contracts\GuardStage;
use App\Guards\Opportunities\GuardResult;
use App\Guards\Opportunities\TransitionContext;
use Illuminate\Support\Facades\Gate;

/**
 * Stage 1 of the guard pipeline — Permission (opportunity-lifecycle.md §12.2
 * "Permission guards"). REAL.
 *
 * Formalises the permission check the action already performs into the pipeline:
 * delegates to {@see Gate::authorize()} on the context's declared permission (the
 * same ability the action checks, e.g. `opportunities.edit`). A missing permission
 * throws Laravel's AuthorizationException (→ 403), exactly as a bare
 * `Gate::authorize()` would. When the context declares no permission the stage is
 * a no-op (some internal/system transitions are unguarded).
 */
class PermissionStage implements GuardStage
{
    public function evaluate(TransitionContext $context): GuardResult
    {
        if ($context->permission === null) {
            return GuardResult::allow();
        }

        // Throws AuthorizationException (403) on failure — the faithful behaviour
        // for a permission denial, matching the actions' existing Gate::authorize.
        Gate::authorize($context->permission);

        return GuardResult::allow();
    }
}
