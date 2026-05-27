<?php

namespace App\Actions\Rates;

use App\Events\AuditableEvent;
use App\Models\RateDefinition;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteRateDefinition
{
    /**
     * @throws ValidationException when the definition is a framework preset
     */
    public function __invoke(RateDefinition $definition): void
    {
        Gate::authorize('rates.delete');

        // Presets are framework-shipped and re-seeded on install; deleting one
        // would be silently resurrected. They may be duplicated and edited, but
        // never deleted (mirrors the admin UI, which only deletes custom rows).
        if ($definition->is_preset) {
            throw ValidationException::withMessages([
                'rate_definition' => 'Preset rate definitions cannot be deleted. Duplicate it to create an editable copy instead.',
            ]);
        }

        DB::transaction(function () use ($definition): void {
            event(new AuditableEvent($definition, 'rate_definition.deleted'));

            app(WebhookService::class)->dispatch('rate_definition.deleted', [
                'id' => $definition->id,
            ]);

            $definition->delete();
        });
    }
}
