<?php

namespace App\Settings;

class EmailSettings extends SettingsDefinition
{
    public function group(): string
    {
        return 'email';
    }

    public function defaults(): array
    {
        return [
            'mailer' => 'log',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'ses_key' => '',
            'ses_secret' => '',
            'ses_region' => 'eu-west-1',
            'mailgun_domain' => '',
            'mailgun_secret' => '',
            'postmark_token' => '',
            'from_address' => '',
            'from_name' => '',
            'reply_to_address' => '',
        ];
    }

    public function rules(): array
    {
        return [
            'mailer' => ['required', 'string', 'in:smtp,ses,mailgun,postmark,log'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'ses_key' => ['nullable', 'string', 'max:255'],
            'ses_secret' => ['nullable', 'string', 'max:255'],
            'ses_region' => ['nullable', 'string', 'max:50'],
            'mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mailgun_secret' => ['nullable', 'string', 'max:255'],
            'postmark_token' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function types(): array
    {
        return [
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
            'ses_secret' => 'encrypted',
            'mailgun_secret' => 'encrypted',
            'postmark_token' => 'encrypted',
        ];
    }
}
