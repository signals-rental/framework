<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Heal tenants provisioned before the base-currency key was aligned.
 *
 * The setup wizard / CLI used to write the company currency under the orphan key
 * `company.currency`, but the money engine reads `company.base_currency`. Installs
 * that ran setup before that fix therefore have the value under the wrong key and
 * fall back to the GBP default. Copy the orphan value into the canonical key when
 * the canonical key has no stored value. Idempotent + non-destructive (the orphan
 * row is left in place — it is unreferenced and harmless).
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphan = Setting::query()->forKey('company', 'currency')->first();

        if ($orphan === null || $orphan->value === null || $orphan->value === '') {
            return;
        }

        $hasCanonical = Setting::query()->forKey('company', 'base_currency')->exists();

        if (! $hasCanonical) {
            Setting::query()->create([
                'group' => 'company',
                'key' => 'base_currency',
                'value' => $orphan->value,
            ]);
        }
    }

    public function down(): void
    {
        // Data heal — not reversible.
    }
};
