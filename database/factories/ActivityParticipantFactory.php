<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityParticipant>
 */
class ActivityParticipantFactory extends Factory
{
    protected $model = ActivityParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'member_id' => Member::factory(),
            'mute' => false,
        ];
    }
}
