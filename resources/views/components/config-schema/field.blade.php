@props(['field', 'path', 'values' => []])

{{--
    Dispatcher for a single config-schema field. Evaluates server-side
    `visible_when` against the sibling $values, then renders the partial for the
    field's type. Path is the dotted base path of the field's container
    (e.g. "strategyConfig" or "modifierConfigs.multiplier").
--}}
@php
    $visible = true;
    foreach (($field['visible_when'] ?? []) as $condition) {
        if (data_get($values, $condition['field']) != $condition['value']) {
            $visible = false;
            break;
        }
    }
@endphp

@if($visible)
    @switch($field['type'])
        @case('text')
            <x-config-schema.text :field="$field" :path="$path" />
            @break
        @case('number')
            <x-config-schema.number :field="$field" :path="$path" />
            @break
        @case('decimal')
            <x-config-schema.decimal :field="$field" :path="$path" />
            @break
        @case('toggle')
            <x-config-schema.toggle :field="$field" :path="$path" />
            @break
        @case('select')
            <x-config-schema.select :field="$field" :path="$path" />
            @break
        @case('time')
            <x-config-schema.time :field="$field" :path="$path" />
            @break
        @case('group')
            <x-config-schema.group :field="$field" :path="$path" :values="$values" />
            @break
        @case('repeater')
            <x-config-schema.repeater :field="$field" :path="$path" :values="$values" />
            @break
    @endswitch
@endif
