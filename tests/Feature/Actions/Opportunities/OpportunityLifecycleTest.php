<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\UpdateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\MembershipType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates an opportunity as a draft and projects the row', function () {
    $data = CreateOpportunityData::from([
        'subject' => 'Glastonbury 2026 — Main Stage Lighting',
        'reference' => 'PO-12345',
    ]);

    $result = (new CreateOpportunity)($data);

    expect($result)->toBeInstanceOf(OpportunityData::class)
        ->and($result->subject)->toBe('Glastonbury 2026 — Main Stage Lighting')
        ->and($result->state)->toBe(OpportunityState::Draft->value)
        ->and($result->status)->toBe(OpportunityStatus::DraftOpen->statusValue())
        ->and($result->state_label)->toBe('Draft')
        ->and($result->status_label)->toBe('Open')
        ->and($result->availability_phase)->toBe(DemandPhase::Draft->value);

    $this->assertDatabaseHas('opportunities', [
        'id' => $result->id,
        'subject' => 'Glastonbury 2026 — Main Stage Lighting',
        'reference' => 'PO-12345',
        'state' => OpportunityState::Draft->value,
        'status' => OpportunityStatus::DraftOpen->statusValue(),
    ]);

    // The event store recorded exactly one event.
    expect(VerbEvent::query()->count())->toBe(1);
});

it('converts a draft to a quotation', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Quote me']));

    $result = (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    expect($result->state)->toBe(OpportunityState::Quotation->value)
        ->and($result->status)->toBe(OpportunityStatus::QuotationProvisional->statusValue())
        ->and($result->status_label)->toBe('Provisional');

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'state' => OpportunityState::Quotation->value,
        'status' => OpportunityStatus::QuotationProvisional->statusValue(),
    ]);
});

it('converts a quotation to an order', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'To order']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    // An order must have at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard).
    $product = Product::factory()->rental()->bulk()->create();
    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '1',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));

    $result = (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    expect($result->state)->toBe(OpportunityState::Order->value)
        ->and($result->status)->toBe(OpportunityStatus::OrderActive->statusValue())
        ->and($result->status_label)->toBe('Active')
        ->and($result->availability_phase)->toBe(DemandPhase::Committed->value);
});

it('rejects quoting an opportunity that is not a draft', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Already quoted']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    // Quoting again (now a quotation, not a draft) must fail the guard.
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
})->throws(EventNotValidForCurrentState::class);

it('rejects converting a draft straight to an order', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Skip the quote']));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));
})->throws(EventNotValidForCurrentState::class);

it('rejects converting a lost quotation to an order', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Lost deal']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
    (new ChangeOpportunityStatus)(Opportunity::findOrFail($created->id), OpportunityStatus::QuotationLost);

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));
})->throws(EventNotValidForCurrentState::class);

it('changes status within a state and projects it', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Reserve me']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $result = (new ChangeOpportunityStatus)(
        Opportunity::findOrFail($created->id),
        OpportunityStatus::QuotationReserved,
    );

    expect($result->status)->toBe(OpportunityStatus::QuotationReserved->statusValue())
        ->and($result->status_label)->toBe('Reserved')
        ->and($result->availability_phase)->toBe(DemandPhase::Committed->value);

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'status' => OpportunityStatus::QuotationReserved->statusValue(),
    ]);
});

it('rejects a status that does not belong to the current state', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Wrong status']));

    // Draft only has status 0; a Quotation status is not valid while Draft.
    (new ChangeOpportunityStatus)(
        Opportunity::findOrFail($created->id),
        OpportunityStatus::QuotationReserved,
    );
})->throws(EventNotValidForCurrentState::class);

it('rejects changing status when the opportunity is closed/terminal', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Lost then reopened']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
    // Move into a terminal status (Lost).
    (new ChangeOpportunityStatus)(Opportunity::findOrFail($created->id), OpportunityStatus::QuotationLost);

    // Attempting to move it back to an active status must be rejected — a closed
    // opportunity cannot re-consume stock via demand.
    (new ChangeOpportunityStatus)(
        Opportunity::findOrFail($created->id),
        OpportunityStatus::QuotationReserved,
    );
})->throws(EventNotValidForCurrentState::class);

it('still allows a status change for a non-terminal opportunity', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Active still moves']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $result = (new ChangeOpportunityStatus)(
        Opportunity::findOrFail($created->id),
        OpportunityStatus::QuotationReserved,
    );

    expect($result->status)->toBe(OpportunityStatus::QuotationReserved->statusValue());
});

it('updates editable header fields', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Original']));

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from([
            'subject' => 'Updated subject',
            'reference' => 'NEW-REF',
        ]),
    );

    expect($result->subject)->toBe('Updated subject')
        ->and($result->reference)->toBe('NEW-REF');

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'subject' => 'Updated subject',
        'reference' => 'NEW-REF',
    ]);
});

it('clears a nullable field when an explicit null is provided', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Has a reference',
        'reference' => 'PO-CLEAR-ME',
        'description' => 'Internal note',
    ]));

    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        // Explicit null for reference clears the column; description is absent
        // (not passed) so it must remain untouched.
        UpdateOpportunityData::from(['reference' => null]),
    );

    expect($result->reference)->toBeNull()
        ->and($result->description)->toBe('Internal note');

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'reference' => null,
        'description' => 'Internal note',
    ]);
});

it('leaves a nullable field unchanged when its key is omitted', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Keep my reference',
        'reference' => 'PO-KEEP',
    ]));

    // Update only the subject — reference must be left exactly as it was.
    $result = (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['subject' => 'New subject']),
    );

    expect($result->subject)->toBe('New subject')
        ->and($result->reference)->toBe('PO-KEEP');

    $this->assertDatabaseHas('opportunities', [
        'id' => $created->id,
        'subject' => 'New subject',
        'reference' => 'PO-KEEP',
    ]);
});

it('rejects editing a closed opportunity', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Lost then edited']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
    (new ChangeOpportunityStatus)(Opportunity::findOrFail($created->id), OpportunityStatus::QuotationDead);

    (new UpdateOpportunity)(
        Opportunity::findOrFail($created->id),
        UpdateOpportunityData::from(['subject' => 'Should not apply']),
    );
})->throws(EventNotValidForCurrentState::class);

it('denies creating an opportunity without permission', function () {
    $this->actingAs(User::factory()->create());

    (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Unauthorised']));
})->throws(AuthorizationException::class);

it('denies converting an opportunity without permission', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Guarded']));

    $this->actingAs(User::factory()->create());

    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));
})->throws(AuthorizationException::class);

it('rebuilds the projection identically on replay', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Replay me']));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    // An order must carry at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard).
    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => 'Line', 'quantity' => '1', 'unit_price' => 5000,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $id = $created->id;
    $stateId = Opportunity::findOrFail($id)->state_id;
    $before = Opportunity::query()->whereKey($id)
        ->firstOrFail()->only(['id', 'state_id', 'subject', 'state', 'status']);

    // Wipe the projection, then rebuild it purely from the event store. The
    // integer PK is now replay-stable: it is allocated by the action and baked
    // into the OpportunityCreated event, so replay reproduces the same id.
    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    $rebuilt = Opportunity::query()->whereKey($id)->firstOrFail();
    $after = $rebuilt->only(['id', 'state_id', 'subject', 'state', 'status']);

    expect($after)->toEqual($before)
        ->and($rebuilt->id)->toBe($id)
        ->and($rebuilt->state_id)->toBe($stateId)
        ->and($rebuilt->state)->toBe(OpportunityState::Order)
        ->and($rebuilt->status)->toBe(OpportunityStatus::OrderActive->statusValue());
});

it('assigns ascending integer ids to sequential creates', function () {
    $first = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'First']));
    $second = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Second']));

    // The PK is a small allocated integer (NOT a snowflake) and ascends by one.
    expect($first->id)->toBeLessThan(1000)
        ->and($second->id)->toBe($first->id + 1);

    // The state_id bridge remains a large snowflake, distinct from the PK.
    $firstStateId = Opportunity::findOrFail($first->id)->state_id;
    expect($firstStateId)->toBeGreaterThan(1000000);
});

it('preserves both ids across a full replay rebuild', function () {
    $first = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Keep one']));
    $second = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Keep two']));

    $firstId = $first->id;
    $secondId = $second->id;

    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    expect(Opportunity::query()->whereKey($firstId)->exists())->toBeTrue()
        ->and(Opportunity::query()->whereKey($secondId)->exists())->toBeTrue()
        ->and(Opportunity::findOrFail($firstId)->id)->toBe($firstId)
        ->and(Opportunity::findOrFail($secondId)->id)->toBe($secondId);
});

it('rolls back the event and the projection atomically when a projection fails', function () {
    // Force the projection write to fail by removing the table that handle()
    // writes to. The committed event row would already be flushed inside the
    // commitVerbs() transaction, so the whole transaction must roll back —
    // leaving neither an event row nor a projected row.
    Schema::rename('opportunities', 'opportunities_tmp_missing');

    $threw = false;

    try {
        (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Doomed']));
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue()
        ->and(VerbEvent::query()->count())->toBe(0);

    // Restore the projection table with its FULL current schema. Renaming it back
    // (rather than re-running the base migration) keeps this test correct as later
    // migrations add columns, and confirms the in-memory Verbs state was reset
    // cleanly — a fresh create still succeeds as the very first event.
    Schema::rename('opportunities_tmp_missing', 'opportunities');

    $result = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Recovered']));

    expect($result->subject)->toBe('Recovered')
        ->and(VerbEvent::query()->count())->toBe(1);
});

it('round-trips money input into integer minor units', function () {
    // String (major units) and int (already minor units) both normalise to the
    // same stored integer via the MoneyInput cast on charge_total.
    $fromString = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'String money',
        'currency' => 'GBP',
        'charge_total' => '125.50',
    ]));

    $fromInt = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Int money',
        'currency' => 'GBP',
        'charge_total' => 12550,
    ]));

    $this->assertDatabaseHas('opportunities', ['id' => $fromString->id, 'charge_total' => 12550]);
    $this->assertDatabaseHas('opportunities', ['id' => $fromInt->id, 'charge_total' => 12550]);

    // And the response DTO emits it as a decimal string.
    expect($fromString->charge_total)->toBe('125.50')
        ->and($fromInt->charge_total)->toBe('125.50');
});

describe('B7 — opportunity customer must be an organisation', function () {
    it('creates an opportunity with an Organisation member', function () {
        $org = Member::factory()->organisation()->create();

        $result = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Org-backed opportunity',
            'member_id' => $org->id,
        ]));

        expect($result->member_id)->toBe($org->id);
        $this->assertDatabaseHas('opportunities', ['id' => $result->id, 'member_id' => $org->id]);
    });

    it('rejects creating an opportunity with a non-organisation member via the action', function (string $state) {
        $member = Member::factory()->{$state}()->create();

        expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Bad customer',
            'member_id' => $member->id,
        ])))
            ->toThrow(
                ValidationException::class,
                'The opportunity customer must be an organisation.',
            );

        // The genesis event must NOT have been committed.
        $this->assertDatabaseMissing('opportunities', ['subject' => 'Bad customer']);
    })->with(['contact', 'venue', 'user']);

    it('rejects creating an opportunity with a member_id that does not exist', function () {
        expect(fn () => (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Ghost customer',
            'member_id' => 999999,
        ])))->toThrow(ValidationException::class, 'The opportunity customer must be an organisation.');
    });

    it('rejects the CreateOpportunityData DTO validation for a non-organisation member', function () {
        $contact = Member::factory()->contact()->create();

        expect(fn () => CreateOpportunityData::validate([
            'subject' => 'DTO check',
            'member_id' => $contact->id,
        ]))->toThrow(ValidationException::class);

        // An organisation member passes DTO validation.
        $org = Member::factory()->organisation()->create();
        $validated = CreateOpportunityData::validate(['subject' => 'DTO ok', 'member_id' => $org->id]);
        expect($validated['member_id'])->toBe($org->id);
    });

    it('allows changing the customer to another Organisation via update', function () {
        $org = Member::factory()->organisation()->create();
        $newOrg = Member::factory()->organisation()->create();

        $opportunity = Opportunity::query()->whereKey(
            (new CreateOpportunity)(CreateOpportunityData::from([
                'subject' => 'Switchable',
                'member_id' => $org->id,
            ]))->id
        )->firstOrFail();

        $result = (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from([
            'member_id' => $newOrg->id,
        ]));

        expect($result->member_id)->toBe($newOrg->id);
    });

    it('rejects changing the customer to a non-organisation via update', function () {
        $org = Member::factory()->organisation()->create();
        $contact = Member::factory()->contact()->create();

        $opportunity = Opportunity::query()->whereKey(
            (new CreateOpportunity)(CreateOpportunityData::from([
                'subject' => 'Locked customer',
                'member_id' => $org->id,
            ]))->id
        )->firstOrFail();

        expect(fn () => (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from([
            'member_id' => $contact->id,
        ])))->toThrow(
            ValidationException::class,
            'The opportunity customer must be an organisation.',
        );

        // The customer is unchanged.
        expect($opportunity->refresh()->member_id)->toBe($org->id);
    });

    it('leaves the customer unchanged on an update that omits member_id', function () {
        $org = Member::factory()->organisation()->create();

        $opportunity = Opportunity::query()->whereKey(
            (new CreateOpportunity)(CreateOpportunityData::from([
                'subject' => 'Untouched customer',
                'member_id' => $org->id,
            ]))->id
        )->firstOrFail();

        (new UpdateOpportunity)($opportunity, UpdateOpportunityData::from(['reference' => 'PO-NEW']));

        expect($opportunity->refresh()->member_id)->toBe($org->id);
    });

    it('confirms the membership type used is Organisation', function () {
        expect(MembershipType::Organisation->value)->toBe('organisation');
    });
});
