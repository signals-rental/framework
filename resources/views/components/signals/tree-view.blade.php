@props([
    'items' => [],
    'selectable' => false,
])

<div
    {{ $attributes->merge(['class' => 's-tree']) }}
    x-data="{ selected: null, expanded: [], toggle(id) { const i = this.expanded.indexOf(id); i === -1 ? this.expanded.push(id) : this.expanded.splice(i, 1); }, isExpanded(id) { return this.expanded.includes(id); }, select(id) { this.selected = this.selected === id ? null : id; } }"
>
    @foreach($items as $index => $item)
        @include('components.signals.partials.tree-node', ['node' => $item, 'nodeId' => "node-{$index}", 'selectable' => $selectable])
    @endforeach
</div>
