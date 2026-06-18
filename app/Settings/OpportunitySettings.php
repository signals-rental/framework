<?php

namespace App\Settings;

use App\Services\Opportunities\OpportunityNumberGenerator;
use Illuminate\Validation\Rule;

/**
 * Settings for the event-sourced opportunity lifecycle.
 *
 * The opportunity `number` is a zero-padded RMS-style reference (e.g.
 * "0000000042") allocated from a per-store sequence at create time and baked
 * into the OpportunityCreated event so replay reproduces it verbatim.
 *
 *  - `number_pad`   — the zero-pad width of the generated number.
 *  - `number_scope` — 'store' (one running sequence per store, matching RMS) or
 *                     'global' (one shared sequence across all stores).
 *
 * These are read at event-fire time only (in
 * {@see OpportunityNumberGenerator}); replay
 * re-applies the stored event payload and never consults the generator, so
 * sourcing them from settings does not affect replay determinism.
 */
class OpportunitySettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'opportunities';
    }

    public function defaults(): array
    {
        return [
            'number_pad' => 10,
            'number_scope' => 'store',
        ];
    }

    public function rules(): array
    {
        return [
            'number_pad' => ['required', 'integer', 'min:0', 'max:20'],
            'number_scope' => ['required', 'string', Rule::in(['store', 'global'])],
        ];
    }

    public function types(): array
    {
        return [
            'number_pad' => 'integer',
            'number_scope' => 'string',
        ];
    }
}
