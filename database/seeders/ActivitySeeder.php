<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        if (! $user) {
            return;
        }

        $members = Member::limit(3)->get();

        // Create a mix of activity types
        Activity::factory()->task()->create([
            'subject' => 'Follow up on rental quote',
            'owned_by' => $user->id,
            'regarding_type' => $members->isNotEmpty() ? Member::class : null,
            'regarding_id' => $members->first()?->id,
            'starts_at' => now()->addDay(),
        ]);

        Activity::factory()->call()->create([
            'subject' => 'Confirm delivery schedule',
            'owned_by' => $user->id,
            'starts_at' => now()->addHours(3),
        ]);

        Activity::factory()->meeting()->create([
            'subject' => 'Site visit for upcoming event',
            'owned_by' => $user->id,
            'location' => 'Client Office',
        ]);

        Activity::factory()->email()->create([
            'subject' => 'Send updated price list',
            'owned_by' => $user->id,
        ]);

        Activity::factory()->note()->create([
            'subject' => 'Customer prefers Friday deliveries',
            'description' => 'Noted during last phone call that Friday mornings work best for the customer.',
            'owned_by' => $user->id,
            'regarding_type' => $members->count() > 1 ? Member::class : null,
            'regarding_id' => $members->count() > 1 ? $members[1]->id : null,
        ]);

        Activity::factory()->completed()->create([
            'subject' => 'Initial consultation completed',
            'owned_by' => $user->id,
        ]);
    }
}
