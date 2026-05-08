@props(['height' => 'h-4', 'width' => 'w-full', 'rounded' => 'rounded-lg', 'class' => ''])
{{--
  Animated shimmer skeleton line.
  Usage: <x-skeleton height="h-5" width="w-3/4" />
         <x-skeleton height="h-10" rounded="rounded-xl" />
--}}
<div {{ $attributes->merge(['class' => "rd-shimmer $height $width $rounded $class"]) }}
     aria-hidden="true"></div>
