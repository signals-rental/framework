<?php

namespace App\Views;

class Column
{
    public function __construct(
        public readonly string $key,
        public string $label = '',
        public ?int $width = null,
        public bool $sortable = false,
        public bool $filterable = false,
        public string $type = 'string',
    ) {
        if ($this->label === '') {
            $this->label = str($this->key)->replace('_', ' ')->title()->toString();
        }
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function filterable(bool $filterable = true): self
    {
        $this->filterable = $filterable;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
