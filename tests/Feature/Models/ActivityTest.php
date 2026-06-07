<?php

use App\Enums\ActivityPriority;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\TimeStatus;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\SchemaRegistry;

it('has correct fillable attributes', function () {
    $activity = new Activity;

    expect($activity->getFillable())->toContain(
        'subject',
        'description',
        'location',
        'regarding_id',
        'regarding_type',
        'owned_by',
        'starts_at',
        'ends_at',
        'priority',
        'type_id',
        'status_id',
        'completed',
        'time_status',
        'tag_list',
    );
});

it('creates an activity with factory defaults', function () {
    $activity = Activity::factory()->create(['subject' => 'Test Activity']);

    expect($activity->subject)->toBe('Test Activity')
        ->and($activity->type_id)->toBe(ActivityType::Task)
        ->and($activity->status_id)->toBe(ActivityStatus::Scheduled)
        ->and($activity->priority)->toBe(ActivityPriority::Normal)
        ->and($activity->completed)->toBeFalse();
});

it('casts type_id to ActivityType enum', function () {
    $activity = Activity::factory()->create();

    expect($activity->type_id)->toBeInstanceOf(ActivityType::class);
});

it('casts status_id to ActivityStatus enum', function () {
    $activity = Activity::factory()->create();

    expect($activity->status_id)->toBeInstanceOf(ActivityStatus::class);
});

it('casts priority to ActivityPriority enum', function () {
    $activity = Activity::factory()->create();

    expect($activity->priority)->toBeInstanceOf(ActivityPriority::class);
});

it('casts time_status to TimeStatus enum', function () {
    $activity = Activity::factory()->create();

    expect($activity->time_status)->toBeInstanceOf(TimeStatus::class);
});

it('belongs to an owner (user)', function () {
    $user = User::factory()->create();
    $activity = Activity::factory()->create(['owned_by' => $user->id]);

    expect($activity->owner->id)->toBe($user->id);
});

it('has polymorphic regarding relationship', function () {
    $member = Member::factory()->create();
    $activity = Activity::factory()->forMember($member)->create();

    expect($activity->regarding)->toBeInstanceOf(Member::class);
    /** @var Member $regarding */
    $regarding = $activity->regarding;
    expect($regarding->id)->toBe($member->id);
});

it('has many participants', function () {
    $activity = Activity::factory()->create();
    $member = Member::factory()->create();
    ActivityParticipant::factory()->create([
        'activity_id' => $activity->id,
        'member_id' => $member->id,
    ]);

    expect($activity->participants)->toHaveCount(1)
        ->and($activity->participants->first()->member_id)->toBe($member->id);
});

it('has participantMembers many-to-many', function () {
    $activity = Activity::factory()->create();
    $member = Member::factory()->create();
    ActivityParticipant::factory()->create([
        'activity_id' => $activity->id,
        'member_id' => $member->id,
    ]);

    expect($activity->participantMembers)->toHaveCount(1)
        ->and($activity->participantMembers->first()->id)->toBe($member->id);
});

it('scopes to activities for a member', function () {
    $member = Member::factory()->create();
    Activity::factory()->forMember($member)->create();
    Activity::factory()->create();

    expect(Activity::forMember($member->id)->count())->toBe(1);
});

it('scopes to activities for a product', function () {
    $product = Product::factory()->create();
    Activity::factory()->forProduct($product)->create();
    Activity::factory()->create();

    expect(Activity::forProduct($product->id)->count())->toBe(1);
});

it('scopes to pending activities', function () {
    Activity::factory()->create(['completed' => false]);
    Activity::factory()->completed()->create();

    expect(Activity::pending()->count())->toBe(1);
});

it('scopes to completed activities', function () {
    Activity::factory()->create(['completed' => false]);
    Activity::factory()->completed()->create();

    expect(Activity::completed()->count())->toBe(1);
});

it('scopes to overdue activities', function () {
    Activity::factory()->overdue()->create();
    Activity::factory()->create(['starts_at' => now()->addDay()]);

    expect(Activity::overdue()->count())->toBe(1);
});

it('scopes to activities owned by a user', function () {
    $user = User::factory()->create();
    Activity::factory()->create(['owned_by' => $user->id]);
    Activity::factory()->create();

    expect(Activity::ownedBy($user->id)->count())->toBe(1);
});

it('member has activities morphMany', function () {
    $member = Member::factory()->create();
    Activity::factory()->forMember($member)->count(2)->create();

    expect($member->activities)->toHaveCount(2);
});

it('scopes to activities of a specific type', function () {
    Activity::factory()->create(['type_id' => ActivityType::Task]);
    Activity::factory()->create(['type_id' => ActivityType::Call]);
    Activity::factory()->create(['type_id' => ActivityType::Task]);

    expect(Activity::ofType(ActivityType::Task)->count())->toBe(2);
    expect(Activity::ofType(ActivityType::Call)->count())->toBe(1);
});

it('scopes to activities with a specific status', function () {
    Activity::factory()->create(['status_id' => ActivityStatus::Scheduled]);
    Activity::factory()->create(['status_id' => ActivityStatus::Completed]);
    Activity::factory()->create(['status_id' => ActivityStatus::Scheduled]);

    expect(Activity::ofStatus(ActivityStatus::Scheduled)->count())->toBe(2);
    expect(Activity::ofStatus(ActivityStatus::Completed)->count())->toBe(1);
});

it('scopes to activities for a stock level', function () {
    $stockLevel = StockLevel::factory()->create();
    Activity::factory()->forStockLevel($stockLevel)->create();
    Activity::factory()->create();

    expect(Activity::forStockLevel($stockLevel->id)->count())->toBe(1);
});

it('resolves a RMS short regarding_type to a class name', function () {
    expect(Activity::resolveRegardingType('Member'))->toBe(Member::class)
        ->and(Activity::resolveRegardingType('Product'))->toBe(Product::class)
        ->and(Activity::resolveRegardingType('StockLevel'))->toBe(StockLevel::class);
});

it('returns null when resolving a null regarding_type', function () {
    expect(Activity::resolveRegardingType(null))->toBeNull();
});

it('throws for an unknown regarding_type', function () {
    Activity::resolveRegardingType('Unknown');
})->throws(InvalidArgumentException::class);

it('converts a class name to a RMS short regarding_type', function () {
    expect(Activity::shortRegardingType(Member::class))->toBe('Member')
        ->and(Activity::shortRegardingType(Product::class))->toBe('Product')
        ->and(Activity::shortRegardingType(StockLevel::class))->toBe('StockLevel');
});

it('returns null when shortening a null regarding_type', function () {
    expect(Activity::shortRegardingType(null))->toBeNull();
});

it('falls back to class basename for an unmapped regarding_type', function () {
    expect(Activity::shortRegardingType(User::class))->toBe('User');
});

it('defines its schema with core field definitions', function () {
    $schema = (new SchemaRegistry)->resolve(Activity::class);

    expect($schema)->toHaveKeys([
        'subject', 'description', 'location', 'type_id', 'status_id', 'priority',
        'time_status', 'completed', 'regarding_type', 'regarding_id', 'owned_by',
        'starts_at', 'ends_at', 'tag_list',
    ]);
    expect($schema['subject']->required)->toBeTrue()
        ->and($schema['owned_by']->relationType)->toBe('belongsTo');
});
