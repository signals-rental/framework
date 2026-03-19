<?php

namespace App\Console\Commands;

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $failed = 0;

        foreach ($users as $user) {
            try {
                DB::transaction(function () use ($user, &$created): void {
                    $member = Member::create([
                        'name' => $user->name,
                        'membership_type' => MembershipType::User,
                        'is_active' => $user->is_active,
                    ]);

                    $user->update(['member_id' => $member->id]);
                    $created++;

                    $this->line("  Created member #{$member->id} for user #{$user->id} ({$user->name})");
                });
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  Failed for user #{$user->id} ({$user->name}): {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Done. Created {$created} member record(s).");

        if ($failed > 0) {
            $this->error("{$failed} user(s) failed. Review output above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
