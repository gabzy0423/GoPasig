@props(['icon' => null])

<p
    data-slot="alert-heading"
    {{ $attributes->merge(['class' => 'mb-1 flex items-center font-extrabold text-sm']) }}
>
    @if (filled($icon))
        <x-ui.icon :name="$icon" class="text-[var(--icon-color)] mr-2 inline-block" />
    @endif

    {{ $slot }}
</p>
