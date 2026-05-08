@props(['class' => 'text-primary'])
{{-- Three animated bouncing dots for inline loading states --}}
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-0.5 $class"]) }} aria-hidden="true">
    <span class="rd-dot"></span>
    <span class="rd-dot"></span>
    <span class="rd-dot"></span>
</span>
