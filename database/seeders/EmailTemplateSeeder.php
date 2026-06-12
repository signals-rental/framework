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
                'body_markdown' => "# You're invited\n\nHello {{ user.name }},\n\nYou have been invited to join **{{ company.name }}** on Signals. Accept your invitation to set up your account and get started.\n\n<a class=\"sig-btn\" href=\"{{ invitation.url }}\">Accept Invitation &rarr;</a>\n\nThis invitation will expire in 7 days. If you weren't expecting this, you can safely ignore this email.\n\nRegards,\n**{{ company.name }}**",
                'description' => 'Sent when a new user is invited to the system.',
                'available_merge_fields' => ['user.name', 'user.email', 'company.name', 'invitation.url'],
            ],
            'password_reset' => [
                'name' => 'Password Reset',
                'subject' => 'Reset your password',
                'body_markdown' => "# Reset your password\n\nHello {{ user.name }},\n\nWe received a request to reset the password for your account. Click the button below to choose a new password.\n\n<a class=\"sig-btn\" href=\"{{ reset.url }}\">Reset Password &rarr;</a>\n\nThis link will expire in 60 minutes. If you did not request a password reset, no action is required &mdash; your password will remain unchanged.\n\nRegards,\n**{{ company.name }}**",
                'description' => 'Sent when a user requests a password reset.',
                'available_merge_fields' => ['user.name', 'user.email', 'company.name', 'reset.url'],
            ],
            'test_email' => [
                'name' => 'Test Email',
                'subject' => 'Test email from {{ company.name }}',
                'body_markdown' => "# Email is working\n\nHello,\n\nThis is a test email from **{{ company.name }}** to verify your email configuration is working correctly.\n\nIf you received this email, your email settings are configured properly.\n\nRegards,\n**{{ company.name }}**",
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
