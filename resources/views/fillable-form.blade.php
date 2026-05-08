<x-layouts.app title="{{ $template->name }} — Fill Form — RepetitiveDocs">
<div class="p-4 md:p-8">
<div class="max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 md:mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('templates.editor', $template->id) }}" class="text-muted hover:text-navy transition-colors">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-navy">Fill Your Document</h1>
                <p class="text-sm text-slate mt-0.5">{{ $template->name }}</p>
            </div>
        </div>
        <p class="text-slate text-sm ml-8">Loopi detected these fields from your document</p>
    </div>

    @if(session('error'))
    <div class="mb-6 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
        <x-icon name="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Fixed fields summary ────────────────────────────────── --}}
    @if($fixedVars->isNotEmpty())
    <div class="mb-6 bg-success/5 border border-success/20 rounded-2xl p-5"
         x-data="{ open: false }">
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between text-left">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-success/10 rounded-lg flex items-center justify-center flex-shrink-0">
                    <x-icon name="lock" class="w-4 h-4 text-success" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-navy">
                        Loopi already filled {{ $fixedVars->count() }} fixed field{{ $fixedVars->count() === 1 ? '' : 's' }} for this template
                    </p>
                    <p class="text-xs text-muted">These are automatically used — you don't need to type them again.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0 ml-4">
                <a href="{{ route('templates.editor', $template->id) }}"
                   class="text-xs text-primary hover:underline font-medium hidden sm:inline">
                    Edit fixed fields
                </a>
                <x-icon name="arrow-down" class="w-4 h-4 text-muted transition-transform" :class="open ? 'rotate-180' : ''" />
            </div>
        </button>

        {{-- Expanded fixed fields list --}}
        <div x-show="open" x-cloak class="mt-4 pt-4 border-t border-success/20">
            <div class="space-y-2">
                @foreach($fixedVars as $var)
                <div class="flex items-center justify-between py-2 px-3 bg-white rounded-xl border border-success/10"
                     x-data="{ overriding: false }">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-muted">{{ $var->label }}</p>
                        <p class="text-sm font-medium text-navy truncate">
                            {{ Str::limit($var->fixed_value ?? '—', 60) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 ml-3">
                        <span class="px-2 py-0.5 bg-success/10 text-success text-xs rounded-full font-medium">Fixed</span>
                        <button type="button" @click="overriding = !overriding"
                                class="text-xs text-muted hover:text-primary transition-colors">
                            Use different value
                        </button>
                    </div>
                </div>
                {{-- One-time override input --}}
                <div x-show="false" x-cloak class="px-3 pb-2" id="override-{{ $var->name }}">
                    <input type="text"
                           name="overrides[{{ $var->name }}]"
                           placeholder="Enter a different value for this document only"
                           class="w-full px-3 py-2 border border-warning/30 rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-warning/20 focus:border-warning transition-colors">
                    <p class="text-xs text-warning mt-1">This only affects the current document. The fixed value stays saved.</p>
                </div>
                @endforeach
            </div>
            <a href="{{ route('fixed-fields.review', $template->id) }}"
               class="mt-3 inline-flex items-center gap-1.5 text-xs text-primary hover:underline">
                <x-icon name="settings" class="w-3.5 h-3.5" />
                Manage all saved answers
            </a>
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('fillable-form.generate', $template->id) }}">
        @csrf

        <div class="grid lg:grid-cols-3 gap-6 md:gap-8">

            {{-- ── Form fields ─────────────────────────────────── --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-line p-6 md:p-8">

                @php
                    $typeGroups = [
                        'text'     => $formVars->whereIn('type', ['text', 'select']),
                        'contact'  => $formVars->whereIn('type', ['email', 'phone', 'address']),
                        'date'     => $formVars->where('type', 'date'),
                        'number'   => $formVars->whereIn('type', ['number', 'currency']),
                    ];
                    $groupColors = ['text' => 'primary', 'contact' => 'success', 'date' => 'warning', 'number' => 'warning'];
                    $groupLabels = ['text' => 'Text Fields', 'contact' => 'Contact & Location', 'date' => 'Dates', 'number' => 'Numbers & Amounts'];
                    $stepNum = 0;
                @endphp

                @forelse($formVars as $var)
                @php $dummy = true; @endphp
                @empty
                {{-- All fields are fixed --}}
                <div class="flex flex-col items-center py-12 text-center">
                    <x-icon name="check-circle" class="w-12 h-12 text-success mb-4" />
                    <p class="font-semibold text-navy">All fields are already filled!</p>
                    <p class="text-sm text-slate mt-1">Loopi has fixed values for every field in this template.</p>
                </div>
                @endforelse

                @foreach($typeGroups as $groupKey => $groupVars)
                @if($groupVars->isNotEmpty())
                @php $stepNum++; @endphp

                <div class="{{ !$loop->first ? 'mt-8 pt-8 border-t border-line' : '' }}">
                    <h2 class="text-lg font-semibold text-navy mb-5 flex items-center gap-3">
                        <div class="w-8 h-8 bg-{{ $groupColors[$groupKey] }}/10 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-{{ $groupColors[$groupKey] }} text-sm font-bold">{{ $stepNum }}</span>
                        </div>
                        {{ $groupLabels[$groupKey] }}
                    </h2>

                    <div class="space-y-5">
                        @foreach($groupVars as $var)
                        <div>
                            <label for="field_{{ $var->name }}" class="block text-sm font-medium text-navy mb-1.5">
                                {{ $var->label }}
                                @if($var->is_required)<span class="text-danger ml-0.5">*</span>@endif
                                @if(($var->occurrences ?: 1) > 1)
                                <span class="ml-2 px-1.5 py-0.5 bg-primary/10 text-primary text-xs rounded-full font-normal">
                                    updates {{ $var->occurrences }} places
                                </span>
                                @endif
                                @if($var->isDefault())
                                <span class="ml-2 px-1.5 py-0.5 bg-blue-soft text-slate text-xs rounded-full font-normal">
                                    Default
                                </span>
                                @endif
                            </label>

                            @if($var->type === 'address')
                            <textarea
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                rows="3"
                                placeholder="{{ $var->example_value ?? 'Enter ' . $var->label }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >{{ old('fields.' . $var->name, $var->default_value) }}</textarea>

                            @elseif($var->type === 'currency')
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate font-medium">₱</span>
                                <input
                                    type="text"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name, $var->default_value) }}"
                                    placeholder="{{ $var->example_value ?? '0.00' }}"
                                    class="w-full pl-8 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>
                            <p class="text-xs text-muted mt-1">Currency formatting will be applied automatically</p>

                            @elseif($var->type === 'date')
                            <input
                                type="date"
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                value="{{ old('fields.' . $var->name, $var->default_value) }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >

                            @elseif($var->type === 'email')
                            <div class="relative">
                                <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                                <input
                                    type="email"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name, $var->default_value) }}"
                                    placeholder="{{ $var->example_value ?? 'email@example.com' }}"
                                    class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>

                            @elseif($var->type === 'phone')
                            <div class="relative">
                                <x-icon name="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                                <input
                                    type="tel"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name, $var->default_value) }}"
                                    placeholder="{{ $var->example_value ?? '+63 XXX XXX XXXX' }}"
                                    class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>

                            @else
                            <input
                                type="{{ in_array($var->type, ['number']) ? 'number' : 'text' }}"
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                value="{{ old('fields.' . $var->name, $var->default_value) }}"
                                placeholder="{{ $var->example_value ?? 'Enter ' . $var->label }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >
                            @if($var->isDefault())
                            <p class="text-xs text-muted mt-1">Pre-filled from your template. You can edit this for this document.</p>
                            @endif
                            @endif

                            @error('fields.' . $var->name)
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach

                <div class="mt-8 pt-6 border-t border-line">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-primary text-white py-4 rounded-xl font-semibold hover:bg-primary-dark transition-colors text-sm"
                            data-loading-text="Loopi is building your document…">
                        <x-icon name="sparkles" class="w-5 h-5" />
                        Generate Document
                    </button>
                    <p class="text-xs text-muted text-center mt-3">
                        No copy-paste. Loopi does the repetitive part.
                    </p>
                </div>
            </div>

            {{-- ── Right: Info + summary ───────────────────────── --}}
            <div class="space-y-5">

                @php
                    $repeatingCount  = $formVars->filter(fn($v) => ($v->occurrences ?: 1) > 1)->count();
                    $totalPlacements = $formVars->sum(fn($v) => $v->occurrences ?: 1)
                                     + $fixedVars->sum(fn($v) => $v->occurrences ?: 1);
                @endphp

                {{-- Field count card --}}
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white">
                    <h3 class="font-semibold mb-3 flex items-center gap-2">
                        <x-icon name="file-text" class="w-5 h-5" />
                        {{ $template->name }}
                    </h3>
                    <div class="space-y-2 text-sm text-white/90">
                        <p>{{ $formVars->count() }} fields to fill</p>
                        @if($fixedVars->isNotEmpty())
                        <p class="text-white/80">
                            <x-icon name="lock" class="w-3.5 h-3.5 inline" />
                            {{ $fixedVars->count() }} fixed automatically
                        </p>
                        @endif
                        @if($totalPlacements > $formVars->count() + $fixedVars->count())
                        <p class="text-white/80">Updates {{ $totalPlacements }} places in your document</p>
                        @endif
                        @if($template->document_type)
                        <p>Type: {{ $template->document_type }}</p>
                        @endif
                    </div>
                </div>

                {{-- Loopi tip --}}
                <div class="bg-white rounded-2xl border border-line p-5">
                    <div class="flex gap-3">
                        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                             class="w-14 h-14 object-contain flex-shrink-0">
                        <div class="text-sm text-slate">
                            <p class="font-medium text-navy mb-1">Loopi's Tip</p>
                            @if($fixedVars->isNotEmpty() && $repeatingCount > 0)
                            <p>Fixed fields with <span class="text-primary font-medium">updates N places</span> appear multiple times. Edit once and Loopi updates all linked placements.</p>
                            @elseif($fixedVars->isNotEmpty())
                            <p>{{ $fixedVars->count() }} field{{ $fixedVars->count() === 1 ? ' is' : 's are' }} already filled from your saved template settings.</p>
                            @else
                            <p>Fill in all required fields marked with <span class="text-danger font-bold">*</span> to generate your document.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Variable list preview --}}
                <div class="bg-white rounded-2xl border border-line p-5">
                    <h3 class="text-sm font-semibold text-navy mb-3">Fields in this form</h3>
                    <div class="space-y-2">
                        @foreach($formVars as $var)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate truncate mr-2">{{ $var->label }}</span>
                            <span class="px-2 py-0.5 rounded text-xs {{ $var->typeBadgeColor() }} flex-shrink-0">{{ $var->type }}</span>
                        </div>
                        @endforeach
                        @if($fixedVars->isNotEmpty())
                        <div class="pt-2 mt-2 border-t border-line">
                            <p class="text-xs text-muted mb-1">Fixed (auto-filled)</p>
                            @foreach($fixedVars as $var)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-muted truncate mr-2 line-through">{{ $var->label }}</span>
                                <span class="px-2 py-0.5 bg-success/10 text-success text-xs rounded-full flex-shrink-0">Fixed</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </form>

</div>
</div>
</x-layouts.app>
