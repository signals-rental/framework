<?php

use App\Enums\TransactionType;

it('has the correct transaction type values', function () {
    expect(TransactionType::Opening->value)->toBe(1)
        ->and(TransactionType::Buy->value)->toBe(4)
        ->and(TransactionType::Sell->value)->toBe(7)
        ->and(TransactionType::TransferIn->value)->toBe(11);
});

it('returns human-readable labels', function () {
    expect(TransactionType::Opening->label())->toBe('Opening Balance')
        ->and(TransactionType::WriteOff->label())->toBe('Write Off')
        ->and(TransactionType::TransferOut->label())->toBe('Transfer Out');
});

it('returns manual creation values', function () {
    $values = TransactionType::manualCreationValues();
    expect($values)->toContain(4, 5, 6, 7, 9);
    expect($values)->not->toContain(1);
    expect($values)->not->toContain(2);
    expect($values)->not->toContain(3);
});

it('returns correct quantity sign', function () {
    expect(TransactionType::Buy->quantitySign())->toBe(1)
        ->and(TransactionType::Sell->quantitySign())->toBe(-1)
        ->and(TransactionType::WriteOff->quantitySign())->toBe(-1)
        ->and(TransactionType::Opening->quantitySign())->toBe(1);
});
