@props(['grouped', 'values', 'emptyMessage' => 'No custom fields have been configured.'])

@if($grouped->isNotEmpty())
    @foreach($grouped as $groupName => $fields)
        <x-signals.form-section :title="$groupName">
            <dl class="space-y-3">
                @foreach($fields as $field)
                    @php
                        $cfv = $values->get($field->id);
                        $column = $field->field_type->valueColumn();
                        $value = $cfv?->{$column};
                    @endphp
                    <div wire:key="cf-{{ $field->id }}">
                        <dt class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">
                            {{ $field->display_name ?? $field->name }}
                        </dt>
                        <dd class="mt-0.5">
                            @if($field->field_type === \App\Enums\CustomFieldType::Boolean)
                                @if($value === true)
                                    <span class="s-badge s-badge-green">Yes</span>
                                @elseif($value === false)
                                    <span class="s-badge s-badge-zinc">No</span>
                                @else
                                    <span class="text-[var(--text-muted)]">Not set</span>
                                @endif
                            @elseif($field->field_type === \App\Enums\CustomFieldType::Website && $value)
                                <a href="{{ $value }}" target="_blank" rel="noopener noreferrer" class="text-[var(--link)] hover:underline">{{ Str::limit($value, 60) }}</a>
                            @elseif($field->field_type === \App\Enums\CustomFieldType::Email && $value)
                                <a href="mailto:{{ $value }}" class="text-[var(--link)] hover:underline">{{ $value }}</a>
                            @elseif($field->field_type === \App\Enums\CustomFieldType::MultiListOfValues && is_array($value))
                                @if(count($value) > 0)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($value as $item)
                                            <span class="s-badge s-badge-blue">{{ $item }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[var(--text-muted)]">Not set</span>
                                @endif
                            @elseif($value !== null && $value !== '')
                                {{ $value }}
                            @else
                                <span class="text-[var(--text-muted)]">Not set</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-signals.form-section>
    @endforeach
@else
    <div class="text-center text-[var(--text-muted)] py-12">
        {{ $emptyMessage }}
    </div>
@endif
