<?php

namespace Database\Seeders;

use App\Services\NotificationRegistry;
use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Core notification type definitions.
     *
     * @return array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>, is_system?: bool}>
     */
    public static function types(): array
    {
        return [
            'user.invited' => [
                'category' => 'Users',
                'name' => 'User Invited',
                'description' => 'Sent when a new user is invited to the system.',
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
            ],
            'user.deactivated' => [
                'category' => 'Users',
                'name' => 'User Deactivated',
                'description' => 'Sent when a user account is deactivated.',
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
            ],
            'user.reactivated' => [
                'category' => 'Users',
                'name' => 'User Reactivated',
                'description' => 'Sent when a user account is reactivated.',
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
            ],
            'password.reset' => [
                'category' => 'System',
                'name' => 'Password Reset',
                'description' => 'Sent when a password reset is requested.',
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'is_system' => true,
            ],
            'system.test_email' => [
                'category' => 'System',
                'name' => 'Test Email',
                'description' => 'Test notification to verify email delivery.',
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'is_system' => true,
            ],
            // PLACEHOLDER hook (M7-B) for the notifications consumer
            // (shortage-resolution-sub-hires.md §4.6). The waitlist monitor already
            // flips to Matched and fires the durable `shortage.waitlist.matched`
            // audit/event/webhook; registering the NotificationType lets the (unbuilt)
            // notifications consumer attach a "stock is now free" alert with NO
            // retrofit. Actual push delivery is DEFERRED: the monitor carries no
            // recipient column yet, so there is no notifiable to resolve and no
            // Notification class is dispatched — the type is registered so per-user
            // channel preferences exist the moment a recipient is added. `database`
            // is offered (in-app pull) but defaults to none until delivery lands.
            'shortage.waitlist.matched' => [
                'category' => 'Opportunities',
                'name' => 'Waitlist Match',
                'description' => 'Sent when stock a shortage waitlist monitor is waiting on becomes available. Placeholder: delivery is deferred until a recipient is recorded on the monitor.',
                'available_channels' => ['database', 'mail'],
                'default_channels' => [],
            ],
        ];
    }

    public function run(): void
    {
        $registry = app(NotificationRegistry::class);
        $registry->registerMany(self::types());
        $registry->syncToDatabase();
    }
}
