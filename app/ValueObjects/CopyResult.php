<?php

namespace App\ValueObjects;

class CopyResult
{
    /**
     * @param  list<string>  $fieldsCopied
     * @param  list<string>  $fieldsSkipped
     */
    public function __construct(
        public readonly int $copied,
        public readonly int $skipped,
        public readonly array $fieldsCopied = [],
        public readonly array $fieldsSkipped = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'copied' => $this->copied,
            'skipped' => $this->skipped,
            'fields_copied' => $this->fieldsCopied,
            'fields_skipped' => $this->fieldsSkipped,
        ];
    }
}
