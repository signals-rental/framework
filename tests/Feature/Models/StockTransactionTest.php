<?php

use App\Enums\TransactionType;
use App\Models\Member;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;

it('creates a stock transaction with factory defaults', function () {
    $txn = StockTransaction::factory()->create();
    expect($txn->transaction_type)->toBe(TransactionType::Opening)
        ->and($txn->manual)->toBeTrue();
});

it('casts transaction_type to TransactionType enum', function () {
    $txn = StockTransaction::factory()->create();
    expect($txn->transaction_type)->toBeInstanceOf(TransactionType::class);
});

it('belongs to a stock level', function () {
    $stockLevel = StockLevel::factory()->create();
    $txn = StockTransaction::factory()->create(['stock_level_id' => $stockLevel->id]);
    expect($txn->stockLevel->id)->toBe($stockLevel->id);
});

it('belongs to a store', function () {
    $store = Store::factory()->create();
    $txn = StockTransaction::factory()->create(['store_id' => $store->id]);
    expect($txn->store->id)->toBe($store->id);
});

it('calculates positive quantity_move for buy', function () {
    $txn = StockTransaction::factory()->buy()->create(['quantity' => '5.0']);
    expect($txn->quantity_move)->toBe('5.0');
});

it('calculates negative quantity_move for sell', function () {
    $txn = StockTransaction::factory()->sell()->create(['quantity' => '3.0']);
    expect($txn->quantity_move)->toBe('-3.0');
});

it('resolves the polymorphic source relationship', function () {
    $member = Member::factory()->create();
    $txn = StockTransaction::factory()->create([
        'source_id' => $member->id,
        'source_type' => $member->getMorphClass(),
    ]);

    $source = $txn->source;

    expect($source)->toBeInstanceOf(Member::class);
    /** @var Member $source */
    expect($source->id)->toBe($member->id);
});

it('returns null source when no source is set', function () {
    $txn = StockTransaction::factory()->create([
        'source_id' => null,
        'source_type' => null,
    ]);

    expect($txn->source)->toBeNull();
});

it('stock level has many transactions', function () {
    $stockLevel = StockLevel::factory()->create();
    StockTransaction::factory()->count(3)->create(['stock_level_id' => $stockLevel->id]);
    expect($stockLevel->stockTransactions)->toHaveCount(3);
});
