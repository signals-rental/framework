<?php

use App\Actions\Rates\CreateProductRate;
use App\Data\Rates\CreateProductRateData;
use App\Data\Rates\UpdateProductRateData;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

describe('MoneyInput cast — integer passthrough (backward compatible)', function () {
    it('passes an integer price through unchanged as minor units', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => 12550,
            'currency' => 'GBP',
        ]);

        expect($dto->price)->toBe(12550);
    });

    it('passes a zero integer through unchanged', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => 0,
            'currency' => 'GBP',
        ]);

        expect($dto->price)->toBe(0);
    });
});

describe('MoneyInput cast — decimal major units to minor units', function () {
    it('converts a decimal string to minor units for a scale-2 currency', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '125.50',
            'currency' => 'GBP',
        ]);

        expect($dto->price)->toBe(12550);
    });

    it('converts a float to minor units for a scale-2 currency', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => 125.50,
            'currency' => 'GBP',
        ]);

        expect($dto->price)->toBe(12550);
    });

    it('treats a whole-number decimal string as major units', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '125',
            'currency' => 'GBP',
        ]);

        expect($dto->price)->toBe(12500);
    });

    it('yields identical minor units for string and int forms of the same amount', function () {
        $fromString = CreateProductRateData::from([
            'product_id' => 1, 'rate_definition_id' => 1, 'transaction_type' => 'rental',
            'price' => '125.50', 'currency' => 'GBP',
        ]);

        $fromInt = CreateProductRateData::from([
            'product_id' => 1, 'rate_definition_id' => 1, 'transaction_type' => 'rental',
            'price' => 12550, 'currency' => 'GBP',
        ]);

        expect($fromString->price)->toBe(12550)
            ->and($fromInt->price)->toBe(12550)
            ->and($fromString->price)->toBe($fromInt->price);
    });
});

describe('MoneyInput cast — non-2-scale currencies', function () {
    it('handles a zero-scale currency (JPY) where decimal equals minor units', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '125',
            'currency' => 'JPY',
        ]);

        expect($dto->price)->toBe(125);
    });

    it('rejects a fractional amount for a zero-scale currency (JPY)', function () {
        expect(fn () => CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '125.50',
            'currency' => 'JPY',
        ]))->toThrow(ValidationException::class);
    });

    it('handles a three-scale currency (KWD)', function () {
        $dto = CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '1.234',
            'currency' => 'KWD',
        ]);

        expect($dto->price)->toBe(1234);
    });
});

describe('MoneyInput cast — precision rejection', function () {
    it('rejects a decimal string with more places than the currency allows', function () {
        expect(fn () => CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => '1.999',
            'currency' => 'GBP',
        ]))->toThrow(ValidationException::class);
    });

    it('surfaces the rejection on the price field with a validation error', function () {
        try {
            CreateProductRateData::from([
                'product_id' => 1,
                'rate_definition_id' => 1,
                'transaction_type' => 'rental',
                'price' => '1.999',
                'currency' => 'GBP',
            ]);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('price');
        }
    });

    it('rejects an unparseable money amount', function () {
        expect(fn () => CreateProductRateData::from([
            'product_id' => 1,
            'rate_definition_id' => 1,
            'transaction_type' => 'rental',
            'price' => 'not-a-number',
            'currency' => 'GBP',
        ]))->toThrow(ValidationException::class);
    });
});

describe('MoneyInput cast — default currency fallback', function () {
    it('uses the system base currency when none is provided on an update', function () {
        settings()->set('company.base_currency', 'GBP');

        $dto = UpdateProductRateData::from([
            'price' => '99.99',
        ]);

        expect($dto->price)->toBe(9999);
    });
});

describe('MoneyInput cast — create action round-trip', function () {
    it('stores 12550 in the integer price column from a decimal string', function () {
        $product = Product::factory()->create();
        $definition = RateDefinition::factory()->create();

        $dto = CreateProductRateData::from([
            'product_id' => $product->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => 'rental',
            'price' => '125.50',
            'currency' => 'GBP',
        ]);

        $result = (new CreateProductRate)($dto);

        $stored = ProductRate::query()->find($result->id);

        expect($stored->price)->toBe(12550)
            ->and($stored->getRawOriginal('price'))->toBe(12550);
    });

    it('stores 12550 in the integer price column from an integer (legacy client)', function () {
        $product = Product::factory()->create();
        $definition = RateDefinition::factory()->create();

        $dto = CreateProductRateData::from([
            'product_id' => $product->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => 'rental',
            'price' => 12550,
            'currency' => 'GBP',
        ]);

        $result = (new CreateProductRate)($dto);

        $stored = ProductRate::query()->find($result->id);

        expect($stored->price)->toBe(12550);
    });
});
