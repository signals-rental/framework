<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * @return array<string, array{name: string, subject: string, body_markdown: string, description: string, available_merge_fields: list<string>}>
     */
    public static function defaults(): array
    {
        return [
            'user_invited' => [
                'name' => 'User Invitation',
                'subject' => 'You have been invited to {{ company.name }}',
                'body_markdown' => "Hello {{ user.name }},\n\nYou have been invited to join **{{ company.name }}** on Signals.\n\nPlease click the link below to accept your invitation and set up your account:\n\n[Accept Invitation]({{ invitation.url }})\n\nThis invitation will expire in 7 days.\n\nRegards,\n{{ company.name }}",
                'description' => 'Sent when a new user is invited to the system.',
                'available_merge_fields' => ['user.name', 'user.email', 'company.name', 'invitation.url'],
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'subject' => 'Reset your password',
                'body_markdown' => "Hello {{ user.name }},\n\nWe received a request to reset your password. Click the link below to choose a new password:\n\n[Reset Password]({{ reset.url }})\n\nThis link will expire in 60 minutes. If you did not request a password reset, no action is needed.\n\nRegards,\n{{ company.name }}",
                'description' => 'Sent when a user requests a password reset.',
                'available_merge_fields' => ['user.name', 'user.email', 'company.name', 'reset.url'],
            ],
            'test_email' => [
                'name' => 'Test Email',
                'subject' => 'Test email from {{ company.name }}',
                'body_markdown' => "Hello,\n\nThis is a test email from **{{ company.name }}** to verify your email configuration is working correctly.\n\nIf you received this email, your email settings are configured properly.\n\nRegards,\n{{ company.name }}",
                'description' => 'Sent when testing email configuration from admin settings.',
                'available_merge_fields' => ['company.name'],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::defaults() as $key => $data) {
            EmailTemplate::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'subject' => $data['subject'],
                    'body_markdown' => $data['body_markdown'],
                    'description' => $data['description'],
                    'available_merge_fields' => $data['available_merge_fields'],
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
