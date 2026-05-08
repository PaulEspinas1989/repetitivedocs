@php
    $statusClasses = match($var->approval_status) {
        'approved' => 'border-success/30 bg-success/5',
        'rejected' => 'border-danger/30 bg-danger/5',
        default    => 'border-line bg-white',
    };
    $isRepeating    = ($var->occurrences ?: 1) > 1;
    $thisCardHasErr = $errors->any() && session('error_variable_id') === $var->id;
    $initLabel      = json_encode(old('label', $var->label));
    $initType       = json_encode(old('type',  $var->type));
@endphp

<div class="rounded-2xl border-2 {{ $statusClasses }} p-5 transition-all"
     x-data="{ editing: {{ $thisCardHasErr ? 'true' : 'false' }}, label: {{ $initLabel }}, type: {{ $initType }} }">

    {{-- View mode --}}
    <div x-show="!editing">

        {{-- Header row: badges + edit button --}}
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2 py-0.5 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }}">
                    {{ $var->type }}
                </span>

                @if($isRepeating)
                <span class="px-2 py-0.5 rounded-lg text-xs font-medium bg-primary/10 text-primary flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Repeating ×{{ $var->occurrences }}
                </span>
                @endif

                @if($var->approval_status === 'approved')
                <span class="text-xs text-success font-medium flex items-center gap-1">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" /> Approved
                </span>
                @elseif($var->approval_status === 'rejected')
                <span class="text-xs text-danger font-medium flex items-center gap-1">
                    <x-icon name="x" class="w-3.5 h-3.5" /> Rejected
                </span>
                @endif
            </div>

            <button @click="editing = true"
                    class="p-2 text-muted hover:text-primary hover:bg-blue-soft rounded-xl transition-colors flex-shrink-0 ml-2"
                    title="Edit field">
                <x-icon name="pencil" class="w-4 h-4" />
            </button>
        </div>

        {{-- Field info --}}
        <div class="mb-4">
            <h4 class="font-semibold text-navy text-base leading-tight">{{ $var->label }}</h4>
            <p class="text-xs text-muted font-mono mt-0.5">{{ '{{'  }} {{ $var->name }} {{ '}}' }}</p>
            @if($var->example_value)
            <p class="text-sm text-slate mt-1.5">
                e.g. <span class="font-medium text-navy">{{ Str::limit($var->example_value, 80) }}</span>
            </p>
            @endif
            @if($var->description)
            <p class="text-xs text-muted mt-1">{{ $var->description }}</p>
            @endif
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-2">

            @if($var->approval_status === 'pending')
                {{-- Pending: Approve + Reject side by side --}}
                <form method="POST" action="{{ route('templates.variables.approve', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-success text-white py-2.5 rounded-xl text-sm font-medium hover:bg-green-600 transition-colors">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        Approve
                    </button>
                </form>
                <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-danger/10 text-danger py-2.5 rounded-xl text-sm font-medium hover:bg-danger/20 transition-colors">
                        <x-icon name="x" class="w-4 h-4" />
                        Reject
                    </button>
                </form>

            @elseif($var->approval_status === 'approved')
                {{-- Approved: Undo (back to pending) + Reject --}}
                <form method="POST" action="{{ route('templates.variables.undo', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-blue-soft text-slate py-2.5 rounded-xl text-sm font-medium hover:bg-blue-light transition-colors">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        Undo
                    </button>
                </form>
                <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-danger/10 text-danger py-2.5 rounded-xl text-sm font-medium hover:bg-danger/20 transition-colors">
                        <x-icon name="x" class="w-4 h-4" />
                        Reject
                    </button>
                </form>

            @elseif($var->approval_status === 'rejected')
                {{-- Rejected: Approve + Undo (back to pending) --}}
                <form method="POST" action="{{ route('templates.variables.approve', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-success text-white py-2.5 rounded-xl text-sm font-medium hover:bg-green-600 transition-colors">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        Approve
                    </button>
                </form>
                <form method="POST" action="{{ route('templates.variables.undo', [$template->id, $var->id]) }}" class="flex-1">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-1.5 bg-blue-soft text-slate py-2.5 rounded-xl text-sm font-medium hover:bg-blue-light transition-colors">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        Undo
                    </button>
                </form>
            @endif

        </div>
    </div>

    {{-- Edit mode --}}
    <div x-show="editing" x-cloak>
        {{-- Validation errors scoped to this variable only --}}
        @if($thisCardHasErr)
        <div class="mb-3 p-3 bg-danger/10 border border-danger/20 rounded-xl text-xs text-danger">
            @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-navy">Edit Field</h4>
            <button type="button" @click="editing = false"
                    class="p-1.5 text-muted hover:text-navy rounded-lg transition-colors">
                <x-icon name="x" class="w-4 h-4" />
            </button>
        </div>
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
                    Save changes
                </button>
                <button type="button" @click="editing = false"
                        class="px-4 py-2 bg-blue-soft text-slate rounded-xl text-sm hover:bg-blue-light transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>

</div>
