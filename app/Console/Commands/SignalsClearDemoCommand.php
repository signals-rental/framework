<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HasSignalsBranding;
use App\Models\Activity;
use App\Models\Email;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Phone;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;

#[AsCommand(name: 'signals:clear-demo')]
class SignalsClearDemoCommand extends Command
{
    use HasSignalsBranding;

    protected $signature = 'signals:clear-demo
                            {--force : Skip confirmation prompts}';

    protected $description = 'Remove demo data from the database';

    public function handle(): int
    {
        if (! settings('setup.demo_seeded_at')) {
            $this->components->warn('No demo data has been seeded.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && $this->input->isInteractive()) {
            if (! confirm('This will remove all demo data. Continue?', false)) {
                $this->components->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $this->components->info('Removing demo data...');

        // Remove demo members and their cascading relationships
        $demoMemberIds = Member::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->pluck('id');

        if ($demoMemberIds->isNotEmpty()) {
            Email::query()
                ->where('emailable_type', Member::class)
                ->whereIn('emailable_id', $demoMemberIds)
                ->delete();

            Phone::query()
                ->where('phoneable_type', Member::class)
                ->whereIn('phoneable_id', $demoMemberIds)
                ->delete();

            MemberRelationship::query()
                ->where(function ($q) use ($demoMemberIds) {
                    $q->whereIn('member_id', $demoMemberIds)
                        ->orWhereIn('related_member_id', $demoMemberIds);
                })
                ->delete();

            $count = Member::query()
                ->whereJsonContains('tag_list', 'demo-data')
                ->forceDelete();

            $this->components->info("Removed {$count} demo members and their contact details");
        }

        // Remove demo products. Products soft-delete, so force-delete the demo
        // rows to clear them out entirely rather than leaving trashed records.
        $productCount = Product::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->forceDelete();

        if ($productCount > 0) {
            $this->components->info("Removed {$productCount} demo products");
        }

        // Remove demo stores by their demo-data tag (set by DemoDataSeeder), so
        // re-seeding does not accumulate duplicate stores.
        $storeCount = Store::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->delete();

        if ($storeCount > 0) {
            $this->components->info("Removed {$storeCount} demo stores");
        }

        // Remove demo activities by their demo-data tag (set by DemoDataSeeder).
        // Activities are not soft-deleted, so a plain delete clears them.
        $activityCount = Activity::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->delete();

        if ($activityCount > 0) {
            $this->components->info("Removed {$activityCount} demo activities");
        }

        settings()->set('setup.demo_seeded_at', '');

        $this->components->info('Demo data removed.');

        return self::SUCCESS;
    }
}
