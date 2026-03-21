@props(['groupedCustomFields', 'prefix' => 'customFieldValues'])

@if($groupedCustomFields->isNotEmpty())
    @foreach($groupedCustomFields as $groupName => $fields)
        <x-signals.form-section :title="$groupName">
            <div class="space-y-3">
                @foreach($fields as $field)
                    @php $cfLabel = ($field->display_name ?? $field->name); @endphp
                    <div wire:key="cf-edit-{{ $field->id }}">
                        @error("{$prefix}.{$field->name}")
                            <div class="text-xs text-[var(--red)] mb-1">{{ $message }}</div>
                        @enderror
                        @switch($field->field_type)
                            @case(\App\Enums\CustomFieldType::Boolean)
                                <flux:checkbox
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Text)
                            @case(\App\Enums\CustomFieldType::RichText)
                                <flux:textarea
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    rows="2"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Number)
                            @case(\App\Enums\CustomFieldType::Currency)
                            @case(\App\Enums\CustomFieldType::Percentage)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="number"
                                    step="any"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Date)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="date"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::DateTime)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="datetime-local"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Time)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="time"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::ListOfValues)
                                <flux:select
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                >
                                    <option value="">None</option>
                                    @if($field->listName)
                                        @foreach($field->listName->values->where('is_active', true)->sortBy('sort_order') as $lv)
                                            <option value="{{ $lv->id }}">{{ $lv->name }}</option>
                                        @endforeach
                                    @endif
                                </flux:select>
                                @break

                            @case(\App\Enums\CustomFieldType::Email)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="email"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Website)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="url"
                                    placeholder="https://"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Telephone)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="tel"
                                />
                                @break

                            @case(\App\Enums\CustomFieldType::Colour)
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                    type="color"
                                />
                                @break

                            @default
                                <flux:input
                                    wire:model="{{ $prefix }}.{{ $field->name }}"
                                    :label="$cfLabel"
                                    :required="$field->is_required"
                                />
                        @endswitch
                    </div>
                @endforeach
            </div>
        </x-signals.form-section>
    @endforeach
@else
    <x-signals.form-section title="Custom Fields">
        <p class="text-sm text-[var(--text-muted)]">No custom fields configured.</p>
    </x-signals.form-section>
@endif
