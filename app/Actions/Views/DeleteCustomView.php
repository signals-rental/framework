<?php

namespace App\Actions\Views;

use App\Models\CustomView;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteCustomView
{
    public function __invoke(CustomView $view): void
    {
        Gate::authorize('delete', $view);

        if ($view->visibility === 'system') {
            throw ValidationException::withMessages([
                'view' => ['System default views cannot be deleted.'],
            ]);
        }

        $view->delete();
    }
}
