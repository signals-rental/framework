<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * First-run seeder for the activities domain.
 *
 * Activities have no system or reference data — every activity is a user
 * (or demo) record. Sample activities used to live here, but they were demo
 * data masquerading as first-run data: they could not be removed by
 * `signals:clear-demo` because they were never tagged `demo-data`.
 *
 * Sample activities now live in {@see DemoDataSeeder::createDemoActivities()},
 * tagged `demo-data` so the demo lifecycle (seed-demo / clear-demo) owns them.
 * This seeder is intentionally empty.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty — see class docblock. Demo activities are seeded
        // by DemoDataSeeder, not at first-run.
    }
}
