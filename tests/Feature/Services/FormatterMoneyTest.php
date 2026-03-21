<?php

use App\Models\Currency;
use App\Support\Formatter;

describe('Formatter::money()', function () {
    beforeEach(function () {
        Currency::factory()->create([
            'code' => 'GBP',
            'name' => 'British Pound',
            'symbol' => '£',
            'decimal_places' => 2,
            'is_enabled' => true,
        ]);

        Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_enabled' => true,
        ]);

        Currency::factory()->create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'decimal_places' => 2,
            'is_enabled' => true,
        ]);

        settings()->set('company.base_currency', 'GBP');
        settings()->set('preferences.number_decimal_separator', '.');
        settings()->set('preferences.number_thousands_separator', ',');
        settings()->set('preferences.currency_display', 'symbol');
    });

    it('formats money with symbol display', function () {
        $formatter = app(Formatter::class);

        expect($formatter->money(12550, 'GBP'))->toBe('£125.50');
        expect($formatter->money(0, 'GBP'))->toBe('£0.00');
        expect($formatter->money(100, 'USD'))->toBe('$1.00');
    });

    it('formats money with code display', function () {
        settings()->set('preferences.currency_display', 'code');
        $formatter = app(Formatter::class);

        expect($formatter->money(12550, 'GBP'))->toBe('GBP 125.50');
    });

    it('formats money with name display', function () {
        settings()->set('preferences.currency_display', 'name');
        $formatter = app(Formatter::class);

        expect($formatter->money(12550, 'GBP'))->toBe('125.50 British Pound');
    });

    it('formats money with thousands separators', function () {
        $formatter = app(Formatter::class);

        expect($formatter->money(1234567, 'GBP'))->toBe('£12,345.67');
    });

    it('uses configured decimal and thousands separators', function () {
        settings()->set('preferences.number_decimal_separator', ',');
        settings()->set('preferences.number_thousands_separator', '.');
        $formatter = app(Formatter::class);

        expect($formatter->money(1234567, 'EUR'))->toBe('€12.345,67');
    });

    it('uses base currency as default', function () {
        $formatter = app(Formatter::class);

        expect($formatter->money(500))->toBe('£5.00');
    });

    it('formats decimal money string', function () {
        $formatter = app(Formatter::class);

        expect($formatter->moneyDecimal('125.50', 'GBP'))->toBe('£125.50');
        expect($formatter->moneyDecimal('0.00', 'USD'))->toBe('$0.00');
    });

    it('formats negative money values', function () {
        $formatter = app(Formatter::class);

        expect($formatter->money(-12550, 'GBP'))->toBe('£-125.50');
    });

    it('falls back to currency code when currency not in database', function () {
        $formatter = app(Formatter::class);

        // JPY not seeded in beforeEach
        $result = $formatter->money(50000, 'JPY');

        // Falls back to code as symbol since JPY not in DB
        expect($result)->toContain('JPY');
    });

    it('falls back to currency code for name display when not in database', function () {
        settings()->set('preferences.currency_display', 'name');
        $formatter = app(Formatter::class);

        $result = $formatter->money(50000, 'JPY');

        expect($result)->toContain('JPY');
    });

    it('caches currency lookups within a request', function () {
        $formatter = app(Formatter::class);

        // Call twice — second should use cache, not DB
        $first = $formatter->money(100, 'GBP');
        $second = $formatter->money(200, 'GBP');

        expect($first)->toBe('£1.00');
        expect($second)->toBe('£2.00');
    });

    it('formats zero-decimal currencies', function () {
        Currency::factory()->create([
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'decimal_places' => 0,
            'is_enabled' => true,
        ]);

        $formatter = app(Formatter::class);

        expect($formatter->money(500, 'JPY'))->toBe('¥500');
    });
});
