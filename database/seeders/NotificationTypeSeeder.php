<?php

namespace Database\Seeders;

use App\Services\NotificationRegistry;
use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Core notification type definitions.
     *
     * @return array<string, array{category: string, name: string, description: string, available_channels: list<string>, default_channels: list<string>}>
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
            ],
            'system.test_email' => [
                'category' => 'System',
                'name' => 'Test Email',
                'description' => 'Test notification to verify email delivery.',
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
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
