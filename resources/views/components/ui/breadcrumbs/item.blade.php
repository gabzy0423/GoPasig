@props([
    'separator' => null,
    'iconVariant' => 'mini',
    'icon' => null,
    'href' => null,
])

@php
    $classes = ['group/breadcrumbs flex items-center gap-x-0.5'];

    $linkClasses = [
        'text-sm flex items-center gap-x-1'
    ];

    $staticTextClasses = [
        'text-slate-900 dark:text-gray-300 text-sm flex items-center gap-x-1'
    ];

    $iconClasses = [
        'size-5' => $iconVariant === 'outline' 
    ];
@endphp

<div class="{{ Arr::toCssClasses($classes) }}">
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->class(Arr::toCssClasses($linkClasses)) }}>
            @if ($icon)
                <x-ui.icon name="{{ $icon }}" variant="{{ $iconVariant }}"
                    class="{{ Arr::toCssClasses($iconClasses) }}" />
            @endif
            {{ $slot }}
        </a>
    @else
        <div {{ $attributes->class(Arr::toCssClasses($staticTextClasses)) }}>
            @if ($icon)
                <x-ui.icon name="{{ $icon }}" variant="{{ $iconVariant }}"
                    class="{{ Arr::toCssClasses($iconClasses) }}" />
            @endif
            {{ $slot }}
        </div>
    @endif

    @if ($separator == null)
        <i class="ti ti-chevron-right text-xs text-slate-900 group-last/breadcrumbs:hidden rtl:hidden"></i>
        <i class="ti ti-chevron-left text-xs text-slate-900 group-last/breadcrumbs:hidden hidden rtl:inline"></i>
    @elseif (!is_string($separator))
        {{ $separator }}
    @elseif ($separator === 'slash')
        <x-ui.icon name="slash" variant="mini" class="group-last/breadcrumbs:hidden rtl:-scale-x-100" />
    @else
        <x-ui.icon :name="$separator" variant="mini" class="group-last/breadcrumbs:hidden" />
    @endif
</div>
