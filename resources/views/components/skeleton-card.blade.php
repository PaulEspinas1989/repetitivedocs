@props(['rows' => 3, 'hasAvatar' => false, 'hasButton' => true])
{{--
  Full skeleton card with shimmer.
  Usage: <x-skeleton-card rows="4" :has-button="true" />
--}}
<div class="bg-white rounded-2xl border border-line p-5 space-y-3" aria-hidden="true">
    @if($hasAvatar)
    <div class="flex items-center gap-3 mb-4">
        <x-skeleton height="h-10" width="w-10" rounded="rounded-full" />
        <div class="flex-1 space-y-2">
            <x-skeleton height="h-4" width="w-1/2" />
            <x-skeleton height="h-3" width="w-1/3" />
        </div>
    </div>
    @else
    <x-skeleton height="h-5" width="w-2/3" />
    @endif

    @for($i = 0; $i < $rows; $i++)
    <x-skeleton height="h-3" width="{{ $i === $rows - 1 ? 'w-1/2' : 'w-full' }}" />
    @endfor

    @if($hasButton)
    <div class="pt-2">
        <x-skeleton height="h-9" width="w-32" rounded="rounded-xl" />
    </div>
    @endif
</div>
