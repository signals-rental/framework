<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

/**
 * Invoke DemoDataSeeder::createDemoActivities() in isolation (a private method)
 * without triggering the multi-thousand-row member seed in run(). A silent
 * command is attached so the seeder's $this->command->info() calls succeed.
 */
function seedDemoActivities(): void
{
    $command = new Command;
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

    $seeder = (new DemoDataSeeder)->setContainer(app())->setCommand($command);

    (new ReflectionMethod(DemoDataSeeder::class, 'createDemoActivities'))->invoke($seeder);
}

it('creates demo activities tagged demo-data', function () {
    User::factory()->create();

    seedDemoActivities();

    $demoActivities = Activity::query()->whereJsonContains('tag_list', 'demo-data')->get();

    expect($demoActivities)->not->toBeEmpty();
    $demoActivities->each(function (Activity $activity): void {
        expect($activity->tag_list)->toContain('demo-data');
    });
});

it('seeds a mix of activity types', function () {
    User::factory()->create();

    seedDemoActivities();

    $types = Activity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->pluck('type_id')
        ->unique();

    expect($types->count())->toBeGreaterThan(1);
});

it('links some demo activities to demo members as regarding', function () {
    User::factory()->create();
    Member::factory()->contact()->create(['tag_list' => ['demo-data']]);

    seedDemoActivities();

    $linked = Activity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('regarding_type', Member::class)
        ->whereNotNull('regarding_id')
        ->count();

    expect($linked)->toBeGreaterThan(0);
});

it('is idempotent on re-run', function () {
    User::factory()->create();

    seedDemoActivities();
    $firstCount = Activity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoActivities();
    $secondCount = Activity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});

it('does not seed if no user exists', function () {
    seedDemoActivities();

    expect(Activity::query()->whereJsonContains('tag_list', 'demo-data')->exists())->toBeFalse();
});

it('does not seed demo activities at first-run via ActivitySeeder', function () {
    User::factory()->create();
    Member::factory()->count(3)->create();

    $this->seed(ActivitySeeder::class);

    // ActivitySeeder is now empty — first-run must not create demo activities.
    expect(Activity::query()->count())->toBe(0);
});
