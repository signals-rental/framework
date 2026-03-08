@php
    $hasChildren = !empty($node['children']);
    $label = $node['label'] ?? '';
    $icon = $node['icon'] ?? null;
@endphp

<div class="s-tree-node" x-bind:class="{ 'selected': selected === @js($nodeId) }">
    <div class="s-tree-node-row" @if($selectable) x-on:click="select(@js($nodeId))" @endif>
        <span
            class="s-tree-node-toggle {{ !$hasChildren ? 'leaf' : '' }}"
            x-bind:class="{ 'expanded': isExpanded(@js($nodeId)) }"
            @if($hasChildren) x-on:click.stop="toggle(@js($nodeId))" @endif
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </span>
        @if($icon)
            <span class="s-tree-node-icon">{!! $icon !!}</span>
        @endif
        <span class="s-tree-node-label">{{ $label }}</span>
    </div>
    @if($hasChildren)
        <div class="s-tree-node-children" x-show="isExpanded(@js($nodeId))" x-cloak x-collapse>
            @foreach($node['children'] as $childIndex => $child)
                @include('components.signals.partials.tree-node', ['node' => $child, 'nodeId' => "{$nodeId}-{$childIndex}", 'selectable' => $selectable])
            @endforeach
        </div>
    @endif
</div>
