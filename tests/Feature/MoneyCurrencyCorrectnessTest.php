<?php

use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Models\ExchangeRate;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
use App\Models\User;
use App\ValueObjects\TaxResult;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(CurrencySeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/*
 * FIX 1 — currency-aware decimal scale via FormatsMoney::formatMoneyCost.
 */
describe('formatMoneyCost currency-aware scale', function () {
    it('formats a 0dp currency (JPY) without padding', function () {
        $opportunity = Opportunity::factory()->create([
            'currency_code' => 'JPY',
            'charge_total' => 100000, // 100000 yen — JPY has 0 minor digits
        ]);

        expect($opportunity->formatMoneyCost('charge_total'))->toBe('100000');
    });

    it('formats a 3dp currency (KWD) without truncating fils', function () {
        $opportunity = Opportunity::factory()->create([
            'currency_code' => 'KWD',
            'charge_total' => 1234567, // 1234.567 KWD
        ]);

        expect($opportunity->formatMoneyCost('charge_total'))->toBe('1234.567');
    });

    it('keeps 2dp currencies (GBP) unchanged — no regression', function () {
        $opportunity = Opportunity::factory()->create([
            'currency_code' => 'GBP',
            'charge_total' => 12550,
        ]);

        expect($opportunity->formatMoneyCost('charge_total'))->toBe('125.50');
    });

    it('falls back to the company base currency when the record has none', function () {
        // Base currency setting defaults to GBP (2dp) — never a hardcoded literal.
        $opportunity = Opportunity::factory()->make([
            'currency_code' => null,
            'charge_total' => 9999,
        ]);

        expect($opportunity->formatMoneyCost('charge_total'))->toBe('99.99');
    });
});

/*
 * FIX 1 — TaxResult string-arithmetic decimal output at the currency exponent.
 */
describe('TaxResult::toDecimalString', function () {
    it('renders a 2dp currency exactly (no regression)', function () {
        $result = new TaxResult(
            taxRateName: 'VAT',
            ratePercentage: '20.00',
            netAmount: 12550,
            taxAmount: 2510,
            grossAmount: 15060,
            currencyCode: 'GBP',
        );

        expect($result->netAmountDecimal())->toBe('125.50')
            ->and($result->taxAmountDecimal())->toBe('25.10')
            ->and($result->grossAmountDecimal())->toBe('150.60');
    });

    it('renders a 0dp currency (JPY) as a bare integer string', function () {
        $result = new TaxResult(
            taxRateName: 'CT',
            ratePercentage: '10.00',
            netAmount: 1000,
            taxAmount: 100,
            grossAmount: 1100,
            currencyCode: 'JPY',
        );

        expect($result->netAmountDecimal())->toBe('1000')
            ->and($result->taxAmountDecimal())->toBe('100')
            ->and($result->grossAmountDecimal())->toBe('1100');
    });

    it('renders a 3dp currency (KWD) without losing fils', function () {
        $result = new TaxResult(
            taxRateName: 'KWD tax',
            ratePercentage: '5.00',
            netAmount: 1234567,
            taxAmount: 61728,
            grossAmount: 1296295,
            currencyCode: 'KWD',
        );

        expect($result->netAmountDecimal())->toBe('1234.567')
            ->and($result->taxAmountDecimal())->toBe('61.728')
            ->and($result->grossAmountDecimal())->toBe('1296.295');
    });

    it('uses string arithmetic so large magnitudes keep every minor unit', function () {
        // A float division of this many pence would drift; string arithmetic does not.
        $result = new TaxResult(
            taxRateName: 'VAT',
            ratePercentage: '20.00',
            netAmount: 9007199254740993, // > 2^53, beyond exact float integer range
            taxAmount: 0,
            grossAmount: 9007199254740993,
            currencyCode: 'GBP',
        );

        expect($result->netAmountDecimal())->toBe('90071992547409.93');
    });
});

/*
 * FIX 2 — exchange rate snapshotted at creation via CurrencyService.
 */
describe('exchange rate snapshot at opportunity creation', function () {
    it('snapshots the looked-up rate for a non-base currency', function () {
        ExchangeRate::factory()->create([
            'source_currency_code' => 'EUR',
            'target_currency_code' => 'GBP',
            'rate' => '0.85000000',
            'inverse_rate' => '1.17647059',
            'effective_at' => now()->subDay(),
        ]);

        $result = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'EUR job',
            'currency' => 'EUR',
        ]));

        $opportunity = Opportunity::findOrFail($result->id);

        expect((float) $opportunity->exchange_rate)->toBe(0.85)
            ->and($opportunity->currency_code)->toBe('EUR');
    });

    it('snapshots exactly 1 for a base-currency opportunity', function () {
        $result = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'GBP job',
            'currency' => 'GBP',
        ]));

        $opportunity = Opportunity::findOrFail($result->id);

        expect((float) $opportunity->exchange_rate)->toBe(1.0);
    });

    it('rejects a non-base currency with no configured rate (422)', function () {
        expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'No rate',
            'currency' => 'JPY',
        ])))->toThrow(ValidationException::class);
    });

    it('preserves the snapshotted rate across a full replay rebuild', function () {
        ExchangeRate::factory()->create([
            'source_currency_code' => 'EUR',
            'target_currency_code' => 'GBP',
            'rate' => '0.85000000',
            'inverse_rate' => '1.17647059',
            'effective_at' => now()->subDay(),
        ]);

        $result = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Replay rate',
            'currency' => 'EUR',
        ]));

        $id = $result->id;
        $rateBefore = (float) Opportunity::findOrFail($id)->exchange_rate;

        Opportunity::query()->withTrashed()->forceDelete();
        Verbs::replay();

        expect((float) Opportunity::findOrFail($id)->exchange_rate)->toBe($rateBefore)
            ->and($rateBefore)->toBe(0.85);
    });
});

/*
 * FIX 4 — opportunity_versions carry their own currency context.
 */
describe('opportunity version currency context', function () {
    it('copies the parent opportunity currency and rate onto the version', function () {
        ExchangeRate::factory()->create([
            'source_currency_code' => 'EUR',
            'target_currency_code' => 'GBP',
            'rate' => '0.85000000',
            'inverse_rate' => '1.17647059',
            'effective_at' => now()->subDay(),
        ]);

        $created = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'EUR quote',
            'currency' => 'EUR',
        ]));

        $opportunity = Opportunity::findOrFail($created->id);
        (new ConvertToQuotation)($opportunity);

        $version = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

        $model = OpportunityVersion::findOrFail($version->id);

        expect($model->currency_code)->toBe('EUR')
            ->and((float) $model->exchange_rate)->toBe(0.85);
    });

    it('formats version totals at the snapshotted currency scale (JPY 0dp)', function () {
        $version = OpportunityVersion::factory()->create([
            'currency_code' => 'JPY',
            'charge_total' => 50000,
            'charge_excluding_tax_total' => 50000,
        ]);

        expect($version->formatMoneyCost('charge_total'))->toBe('50000');
    });

    it('exposes currency_code in OpportunityVersionData', function () {
        $version = OpportunityVersion::factory()->create([
            'currency_code' => 'EUR',
        ]);

        $data = OpportunityVersionData::fromModel($version);

        expect($data->currency_code)->toBe('EUR');
    });
});
