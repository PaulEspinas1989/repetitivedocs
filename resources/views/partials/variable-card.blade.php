@php
    $statusClasses = [
        'approved' => 'border-success/20 bg-success/5',
        'rejected' => 'border-danger/20 bg-danger/5',
        'pending'  => 'border-line bg-white',
    ][$var->approval_status] ?? 'border-line bg-white';
@endphp

<div class="rounded-2xl border-2 {{ $statusClasses }} p-5 transition-all"
     x-data="{ editing: false, label: '{{ addslashes($var->label) }}', type: '{{ $var->type }}' }">

    {{-- View mode --}}
    <div x-show="!editing">
        <div class="flex items-start justify-between mb-3">
            <div class="flex-1 min-w-0 pr-3">
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }}">
                        {{ $var->type }}
                    </span>
                    @if($var->approval_status === 'approved')
                    <span class="text-xs text-success font-medium flex items-center gap-1">
                        <x-icon name="check-circle" class="w-3.5 h-3.5" /> Approved
                    </span>
                    @elseif($var->approval_status === 'rejected')
                    <span class="text-xs text-danger font-medium">Rejected</span>
                    @endif
                </div>
                <h4 class="font-semibold text-navy text-base">{{ $var->label }}</h4>
                <p class="text-xs text-muted font-mono mt-0.5">&#123;&#123; {{ $var->name }} &#125;&#125;</p>
                @if($var->example_value)
                <p class="text-sm text-slate mt-1">e.g. <span class="font-medium">{{ $var->example_value }}</span></p>
                @endif
                @if($var->description)
                <p class="text-xs text-muted mt-1">{{ $var->description }}</p>
                @endif
            </div>
            <button @click="editing = true"
                    class="p-2 text-muted hover:text-primary hover:bg-blue-soft rounded-xl transition-colors flex-shrink-0">
                <x-icon name="eye" class="w-4 h-4" />
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex gap-2">
            @if($var->approval_status !== 'approved')
            <form method="POST" action="{{ route('templates.variables.approve', [$template->id, $var->id]) }}" class="flex-1">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-1.5 bg-success text-white py-2 rounded-xl text-sm font-medium hover:bg-green-600 transition-colors">
                    <x-icon name="check-circle" class="w-4 h-4" />
                    Accept
                </button>
            </form>
            @endif

            @if($var->approval_status !== 'rejected')
            <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-soft text-danger rounded-xl text-sm hover:bg-red-50 transition-colors">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </form>
            @endif

            @if($var->approval_status === 'approved' || $var->approval_status === 'rejected')
            <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}">
                @csrf
                @if($var->approval_status === 'approved')
                <input type="hidden" name="_undo" value="1">
                @endif
                @if($var->approval_status !== 'pending')
                {{-- Allow un-approving: just re-approve or undo --}}
                @endif
            </form>
            @endif
        </div>
    </div>

    {{-- Edit mode --}}
    <div x-show="editing" x-cloak>
        <form method="POST" action="{{ route('templates.variables.update', [$template->id, $var->id]) }}">
            @csrf
            @method('PATCH')
            <div class="space-y-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-navy mb-1">Label</label>
                    <input type="text" name="label" x-model="label"
                           class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-navy mb-1">Type</label>
                    <select name="type" x-model="type"
                            class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors bg-white">
                        @foreach(['text','date','number','currency','email','phone','address','select'] as $t)
                        <option value="{{ $t }}" {{ $var->type === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_required" id="req_{{ $var->id }}"
                           {{ $var->is_required ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-line text-primary focus:ring-primary/20">
                    <label for="req_{{ $var->id }}" class="text-xs text-slate">Required field</label>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit"
                        class="flex-1 flex items-center justify-center gap-1.5 bg-primary text-white py-2 rounded-xl text-sm font-medium hover:bg-primary-dark transition-colors">
                    <x-icon name="check-circle" class="w-4 h-4" />
                    Save
                </button>
                <button type="button" @click="editing = false"
                        class="px-4 py-2 bg-blue-soft text-slate rounded-xl text-sm hover:bg-blue-light transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>

</div>
