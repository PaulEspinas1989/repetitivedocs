@props(['size' => 'sm', 'class' => ''])
{{--
  Sizes: xs (0.75rem), sm (1rem), md (1.25rem), lg (1.5rem)
  Usage: <x-spinner /> or <x-spinner size="md" class="text-white" />
--}}
@php
    $sizes = ['xs' => 'w-3 h-3', 'sm' => 'w-4 h-4', 'md' => 'w-5 h-5', 'lg' => 'w-6 h-6'];
    $dim   = $sizes[$size] ?? $sizes['sm'];
@endphp
<span {{ $attributes->merge(['class' => "rd-spinner inline-block $dim $class", 'aria-hidden' => 'true', 'role' => 'status']) }}></span>
