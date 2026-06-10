@props([
    'icon',
    'label',
    'value',
    'valueClass' => '',
])

<div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
    <div class="stat-title flex items-center gap-2">
        <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" />
        <span>{{ $label }}</span>
    </div>
    <div @class(['stat-value', $valueClass])>{{ $value }}</div>
</div>
