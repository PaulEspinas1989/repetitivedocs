<x-layouts.app title="Automation Map — RepetitiveDocs">
<div class="p-6 lg:p-8">
<div class="max-w-5xl mx-auto">

    @php
        $summary = $template->variableSummary();
        $allVars       = $template->variables;
        $repeatingVars = $allVars->filter(fn($v) => ($v->occurrences ?? 1) > 1);
        $standaloneVars = $allVars->filter(fn($v) => ($v->occurrences ?? 1) === 1);

        $categoryIcons = [
            'people'        => ['icon' => 'user',        'color' => '#2F6BFF', 'label' => 'People & names'],
            'dates'         => ['icon' => 'clock',        'color' => '#22C55E', 'label' => 'Dates'],
            'amounts'       => ['icon' => 'dollar-sign',  'color' => '#FF7043', 'label' => 'Amounts'],
            'locations'     => ['icon' => 'globe',        'color' => '#718096', 'label' => 'Locations'],
            'contacts'      => ['icon' => 'mail',         'color' => '#2F6BFF', 'label' => 'Contact details'],
            'organizations' => ['icon' => 'sparkles',     'color' => '#22C55E', 'label' => 'Organizations'],
        ];
    @endphp

    {{-- Success icon --}}
    <div class="flex justify-center mb-8">
        <div class="w-24 h-24 bg-success/10 rounded-3xl flex items-center justify-center">
            <x-icon name="check-circle" class="w-12 h-12 text-success" />
        </div>
    </div>

    {{-- Header --}}
    <div class="text-center mb-12">
        <h1 class="text-3xl lg:text-4xl font-bold text-navy mb-4">
            AI found {{ $summary['total'] }} possible fields to automate
        </h1>
        <p class="text-lg text-slate">
            Review the detected fields and choose how you'd like to proceed
        </p>
    </div>

    {{-- Category summary cards --}}
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        @foreach ($categoryIcons as $key => $cat)
            @if ($summary['categories'][$key] > 0)
            <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4"
                     style="background-color:{{ $cat['color'] }}20">
                    <x-icon name="{{ $cat['icon'] }}" class="w-6 h-6" style="color:{{ $cat['color'] }}" />
                </div>
                <p class="text-2xl font-bold text-navy mb-1">{{ $summary['categories'][$key] }}</p>
                <p class="text-sm text-slate">{{ $cat['label'] }}</p>
            </div>
            @endif
        @endforeach
        @php $other = $summary['total'] - array_sum($summary['categories']); @endphp
        @if ($other > 0)
        <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg transition-shadow">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background-color:#2F6BFF20">
                <x-icon name="file-text" class="w-6 h-6 text-primary" />
            </div>
            <p class="text-2xl font-bold text-navy mb-1">{{ $other }}</p>
            <p class="text-sm text-slate">Other fields</p>
        </div>
        @endif
    </div>

    {{-- Loopi guidance card --}}
    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-8 text-white mb-8">
        <div class="flex items-start gap-6">
            <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                 class="w-28 h-28 object-contain flex-shrink-0 hidden sm:block"
                 style="mix-blend-mode:multiply;filter:brightness(0) invert(1)">
            <div>
                <h2 class="text-2xl font-bold mb-3">Great job, Loopi found them all!</h2>
                <p class="text-white/90 mb-4 text-sm">
                    I've identified all the fields that typically change in your document. You can accept all
                    suggested fields, review them one by one, or go straight to the editor.
                </p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">{{ $summary['total'] }} fields detected</span>
                    @if($repeatingVars->count() > 0)
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">{{ $repeatingVars->count() }} repeating</span>
                    @endif
                    @if($standaloneVars->count() > 0)
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">{{ $standaloneVars->count() }} standalone</span>
                    @endif
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">Template: {{ $template->name }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick action cards --}}
    <div class="grid md:grid-cols-3 gap-6 mb-12">
        <form method="POST" action="{{ route('templates.approve-all', $template->id) }}">
            @csrf
            <button type="submit"
                    class="w-full bg-white rounded-2xl p-6 border-2 border-line hover:border-primary hover:shadow-lg transition-all text-left group">
                <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                    <x-icon name="check-circle" class="w-6 h-6 text-success" />
                </div>
                <h3 class="text-lg font-semibold text-navy mb-2">Accept all fields</h3>
                <p class="text-sm text-slate mb-4">Approve all {{ $summary['total'] }} detected fields and go to the template editor</p>
                <span class="text-primary text-sm font-medium group-hover:underline">Continue →</span>
            </button>
        </form>

        <a href="{{ route('templates.variables', $template->id) }}"
           class="bg-white rounded-2xl p-6 border-2 border-line hover:border-primary hover:shadow-lg transition-all text-left group block">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <x-icon name="eye" class="w-6 h-6 text-primary" />
            </div>
            <h3 class="text-lg font-semibold text-navy mb-2">Review one by one</h3>
            <p class="text-sm text-slate mb-4">Go through each detected field and approve, edit, or reject individually</p>
            <span class="text-primary text-sm font-medium group-hover:underline">Review fields →</span>
        </a>

        <a href="{{ route('templates.editor', $template->id) }}"
           class="bg-white rounded-2xl p-6 border-2 border-line hover:border-primary hover:shadow-lg transition-all text-left group block">
            <div class="w-12 h-12 bg-blue-soft rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                <x-icon name="sparkles" class="w-6 h-6 text-primary" />
            </div>
            <h3 class="text-lg font-semibold text-navy mb-2">Go to template editor</h3>
            <p class="text-sm text-slate mb-4">Open the full template editor to manually fine-tune variables and settings</p>
            <span class="text-primary text-sm font-medium group-hover:underline">Open editor →</span>
        </a>
    </div>

    {{-- ── Grouped variable lists ─────────────────────────────── --}}

    {{-- REPEATING variables --}}
    @if($repeatingVars->count() > 0)
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary inline-block"></span>
                    Repeating Fields
                    <span class="text-sm font-normal text-slate">({{ $repeatingVars->count() }} — appear multiple times in the document)</span>
                </h2>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('templates.group-action', $template->id) }}">
                    @csrf
                    <input type="hidden" name="group" value="repeating">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-success/10 text-success text-xs font-medium rounded-lg hover:bg-success/20 transition-colors">
                        <x-icon name="check-circle" class="w-3.5 h-3.5" />
                        Accept all repeating
                    </button>
                </form>
                <form method="POST" action="{{ route('templates.group-action', $template->id) }}">
                    @csrf
                    <input type="hidden" name="group" value="repeating">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                        Reject all repeating
                    </button>
                </form>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-line overflow-hidden">
            @foreach ($repeatingVars as $var)
            <div class="flex items-center justify-between px-5 py-4 {{ !$loop->last ? 'border-b border-line' : '' }}">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }} flex-shrink-0">
                        {{ $var->type }}
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-navy truncate">{{ $var->label }}</p>
                            <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs rounded-full flex-shrink-0">
                                ×{{ $var->occurrences }}
                            </span>
                        </div>
                        @if ($var->example_value)
                        <p class="text-xs text-muted truncate">e.g. {{ $var->example_value }}</p>
                        @endif
                    </div>
                </div>
                <span class="ml-4 flex-shrink-0 px-2.5 py-1 rounded-lg text-xs font-medium
                    {{ $var->approval_status === 'approved' ? 'bg-success/10 text-success' : ($var->approval_status === 'rejected' ? 'bg-danger/10 text-danger' : 'bg-blue-soft text-primary') }}">
                    {{ ucfirst($var->approval_status) }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- STANDALONE variables --}}
    @if($standaloneVars->count() > 0)
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-base font-semibold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate inline-block"></span>
                    Standalone Fields
                    <span class="text-sm font-normal text-slate">({{ $standaloneVars->count() }} — appear once in the document)</span>
                </h2>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('templates.group-action', $template->id) }}">
                    @csrf
                    <input type="hidden" name="group" value="standalone">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-success/10 text-success text-xs font-medium rounded-lg hover:bg-success/20 transition-colors">
                        <x-icon name="check-circle" class="w-3.5 h-3.5" />
                        Accept all standalone
                    </button>
                </form>
                <form method="POST" action="{{ route('templates.group-action', $template->id) }}">
                    @csrf
                    <input type="hidden" name="group" value="standalone">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                        Reject all standalone
                    </button>
                </form>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-line overflow-hidden">
            @foreach ($standaloneVars as $var)
            <div class="flex items-center justify-between px-5 py-4 {{ !$loop->last ? 'border-b border-line' : '' }}">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }} flex-shrink-0">
                        {{ $var->type }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-navy truncate">{{ $var->label }}</p>
                        @if ($var->example_value)
                        <p class="text-xs text-muted truncate">e.g. {{ $var->example_value }}</p>
                        @endif
                    </div>
                </div>
                <span class="ml-4 flex-shrink-0 px-2.5 py-1 rounded-lg text-xs font-medium
                    {{ $var->approval_status === 'approved' ? 'bg-success/10 text-success' : ($var->approval_status === 'rejected' ? 'bg-danger/10 text-danger' : 'bg-blue-soft text-primary') }}">
                    {{ ucfirst($var->approval_status) }}
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
</div>
</x-layouts.app>
