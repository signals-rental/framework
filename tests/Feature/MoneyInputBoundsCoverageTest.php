<?php

use App\Data\Rates\CreateProductRateData;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

/**
 * @return array<string, mixed>
 */
function rateInput(int|string|float $price, string $currency = 'GBP'): array
{
    return [
        'product_id' => 1,
        'rate_definition_id' => 1,
        'transaction_type' => 'rental',
        'price' => $price,
        'currency' => $currency,
    ];
}

describe('MoneyInput cast — integer bounds enforcement', function () {
    it('rejects a negative decimal amount with a field-scoped validation error', function () {
        try {
            CreateProductRateData::from(rateInput('-1.00'));
            $this->fail('Expected a ValidationException for a negative amount.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('price')
                ->and($e->errors()['price'][0])->toContain('exceeds the maximum allowed value');
        }
    });

    it('rejects a negative integer (legacy minor-unit client)', function () {
        expect(fn () => CreateProductRateData::from(rateInput(-100)))
            ->toThrow(ValidationException::class);
    });

    it('rejects an amount above the maximum 32-bit minor-unit value', function () {
        // 30,000,000.00 GBP = 3,000,000,000 minor units > MAX_MINOR (2,147,483,647).
        try {
            CreateProductRateData::from(rateInput('30000000.00'));
            $this->fail('Expected a ValidationException for an over-max amount.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('price')
                ->and($e->errors()['price'][0])->toContain('exceeds the maximum allowed value');
        }
    });

    it('accepts an amount exactly at the maximum minor-unit boundary', function () {
        // 21,474,836.47 GBP = 2,147,483,647 minor units == MAX_MINOR.
        $dto = CreateProductRateData::from(rateInput('21474836.47'));

        expect($dto->price)->toBe(2_147_483_647);
    });
});
