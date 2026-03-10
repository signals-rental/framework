<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Str;

class EmailTemplateRenderer
{
    /**
     * Render an email template by key with the given merge data.
     *
     * @param  array<string, mixed>  $data  Flat or nested data for merge field resolution
     * @return array{subject: string, html: string}
     */
    public function render(string $key, array $data = []): array
    {
        $template = EmailTemplate::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->renderTemplate($template, $data);
    }

    /**
     * Render an email template model with the given merge data.
     *
     * @param  array<string, mixed>  $data
     * @return array{subject: string, html: string}
     */
    public function renderTemplate(EmailTemplate $template, array $data = []): array
    {
        $subject = $this->resolveMergeFields($template->subject, $data);
        $body = $this->resolveMergeFields($template->body_markdown, $data);
        $html = Str::markdown($body);

        return [
            'subject' => $subject,
            'html' => $html,
        ];
    }

    /**
     * Replace {{ field.path }} merge field placeholders using safe regex.
     * Supports filters: {{ field | upper }}, {{ field | lower }}, {{ field | default:"fallback" }}
     */
    protected function resolveMergeFields(string $content, array $data): string
    {
        $result = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)(?:\s*\|\s*([a-zA-Z]+)(?::\"([^\"]*)\")?)?\s*\}\}/',
            function (array $matches) use ($data): string {
                $field = $matches[1];
                $filter = $matches[2] ?? null;
                $filterArg = $matches[3] ?? null;

                $value = $this->resolveField($field, $data);

                if ($value === null && $filter === 'default' && $filterArg !== null) {
                    return $filterArg;
                }

                if ($value === null) {
                    return '';
                }

                return match ($filter) {
                    'upper' => strtoupper((string) $value),
                    'lower' => strtolower((string) $value),
                    default => (string) $value,
                };
            },
            $content,
        );

        if ($result === null) {
            throw new \RuntimeException(
                'Failed to process email template merge fields: '.preg_last_error_msg()
            );
        }

        return $result;
    }

    /**
     * Resolve a dot-notation field path from a data array.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveField(string $field, array $data): mixed
    {
        return data_get($data, $field);
    }
}
