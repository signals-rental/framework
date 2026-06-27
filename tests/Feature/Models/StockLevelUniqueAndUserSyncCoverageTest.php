<?php

use App\Models\Member;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Validation\ValidationException;

describe('StockLevel::assertUniqueIdentifiers', function () {
    it('throws when an asset number is already in use', function () {
        StockLevel::factory()->serialised()->create(['asset_number' => 'ASSET-1']);

        StockLevel::assertUniqueIdentifiers('ASSET-1', null);
    })->throws(ValidationException::class, 'This asset / barcode number is already in use.');

    it('throws when a serial number is already in use', function () {
        StockLevel::factory()->serialised()->create(['serial_number' => 'SER-1']);

        StockLevel::assertUniqueIdentifiers(null, 'SER-1');
    })->throws(ValidationException::class, 'This serial number is already in use.');

    it('passes when the only clash is the row being ignored', function () {
        $existing = StockLevel::factory()->serialised()->create(['asset_number' => 'ASSET-2']);

        StockLevel::assertUniqueIdentifiers('ASSET-2', null, $existing->id);

        expect($existing->fresh()->asset_number)->toBe('ASSET-2');
    });

    it('passes when identifiers are blank or unused', function () {
        StockLevel::factory()->serialised()->create(['asset_number' => 'OTHER-ASSET']);

        StockLevel::assertUniqueIdentifiers(null, null);
        StockLevel::assertUniqueIdentifiers('', '');
        StockLevel::assertUniqueIdentifiers('FRESH-ASSET', 'FRESH-SERIAL');

        expect(StockLevel::where('asset_number', 'FRESH-ASSET')->exists())->toBeFalse();
    });
});

describe('User::syncMemberName', function () {
    it('returns early without error when the user has no member', function () {
        // The factory auto-links a User-type member, so build an in-memory user
        // with no member to hit the early `! $this->member_id` guard.
        $user = new User(['name' => 'Memberless', 'member_id' => null]);

        $user->syncMemberName();

        expect($user->member_id)->toBeNull();
    });

    it('propagates a renamed user name to its linked member', function () {
        $member = Member::factory()->create(['name' => 'Old Name']);
        $user = User::factory()->create([
            'member_id' => $member->id,
            'name' => 'New Name',
        ]);

        $user->syncMemberName();

        expect($member->fresh()->name)->toBe('New Name');
    });
});
