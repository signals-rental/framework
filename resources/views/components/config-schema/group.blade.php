@props(['field', 'path', 'values' => []])

{{-- A group is a flat-key visibility/layout container: its children bind to the
     same base $path as the group's siblings, and are shown collectively. --}}
<div class="space-y-4 rounded-lg border border-[var(--border)] p-4">
    <div class="text-sm font-medium text-[var(--text-primary)]">{{ $field['label'] }}</div>
    @if(! empty($field['help']))
        <p class="text-xs text-[var(--text-secondary)]">{{ $field['help'] }}</p>
    @endif
    @foreach(($field['fields'] ?? []) as $child)
        <x-config-schema.field :field="$child" :path="$path" :values="$values" />
    @endforeach
</div>
