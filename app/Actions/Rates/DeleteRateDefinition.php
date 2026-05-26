<?php

namespace App\Actions\Rates;

use App\Events\AuditableEvent;
use App\Models\RateDefinition;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteRateDefinition
{
    public function __invoke(RateDefinition $definition): void
    {
        Gate::authorize('rates.delete');

        DB::transaction(function () use ($definition): void {
            event(new AuditableEvent($definition, 'rate_definition.deleted'));

            app(WebhookService::class)->dispatch('rate_definition.deleted', [
                'id' => $definition->id,
            ]);

            $definition->delete();
        });
    }
}
