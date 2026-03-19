<?php

namespace App\Console\Commands;

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillUserMembers extends Command
{
    protected $signature = 'signals:backfill-user-members';

    protected $description = 'Create User-type member records for existing users that lack one';

    public function handle(): int
    {
        $users = User::query()->whereNull('member_id')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have linked member records.');

            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} user(s) without member records.");

        $created = 0;
        foreach ($users as $user) {
            $member = Member::create([
                'name' => $user->name,
                'membership_type' => MembershipType::User,
                'is_active' => $user->is_active,
            ]);

            $user->update(['member_id' => $member->id]);
            $created++;

            $this->line("  Created member #{$member->id} for user #{$user->id} ({$user->name})");
        }

        $this->info("Done. Created {$created} member record(s).");

        return self::SUCCESS;
    }
}
