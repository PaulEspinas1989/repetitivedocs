<x-layouts.app title="What would you like to do? — RepetitiveDocs">
<div class="p-6 lg:p-12">
<div class="max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="text-center mb-10">
        <div class="flex justify-center mb-6">
            <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                 class="w-24 h-24 object-contain animate-float">
        </div>
        <h1 class="text-3xl font-bold text-navy mb-3">Loopi found the fields!</h1>
        <p class="text-lg text-slate max-w-xl mx-auto">
            Do you want to reuse this document later, or just generate one file right now?
        </p>
    </div>

    {{-- Decision cards --}}
    <div class="grid md:grid-cols-2 gap-6 mb-8">

        {{-- Generate Once --}}
        <form method="POST" action="{{ route('upload-decision.generate-once', $template->id) }}">
            @csrf
            <button type="submit"
                    class="w-full text-left bg-white rounded-2xl border-2 border-line hover:border-primary hover:shadow-lg transition-all p-7 group"
                    data-loading-text="Getting your form ready…">
                <div class="w-14 h-14 bg-blue-soft rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <x-icon name="zap" class="w-7 h-7 text-primary" />
                </div>
                <h2 class="text-xl font-bold text-navy mb-2">Generate once</h2>
                <p class="text-sm text-slate mb-4">
                    Fill this document now without saving it to your template library.
                </p>
                <p class="text-xs text-muted">
                    Best for: One-time edits or documents you may not reuse.
                </p>
                <div class="mt-5 flex items-center gap-2 text-primary text-sm font-semibold group-hover:underline">
                    Continue →
                </div>
            </button>
        </form>

        {{-- Save as Template --}}
        <form method="POST" action="{{ route('upload-decision.save-template', $template->id) }}">
            @csrf
            <button type="submit"
                    class="w-full text-left bg-gradient-to-br from-primary to-primary-dark rounded-2xl border-2 border-transparent hover:shadow-xl transition-all p-7 group text-white"
                    data-loading-text="Saving your template…">
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
                    <x-icon name="layers" class="w-7 h-7 text-white" />
                </div>
                <h2 class="text-xl font-bold mb-2">Save as template</h2>
                <p class="text-sm text-white/90 mb-4">
                    Turn this into a reusable template you can fill again and again.
                </p>
                <p class="text-xs text-white/70">
                    Best for: Documents you generate regularly.
                </p>
                <div class="mt-5 flex items-center gap-2 text-sm font-semibold">
                    <x-icon name="sparkles" class="w-4 h-4" />
                    Save as Template →
                </div>
            </button>
        </form>

    </div>

    {{-- Loopi guidance --}}
    <div class="bg-blue-soft rounded-2xl p-5 border border-line flex gap-4">
        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
             class="w-12 h-12 object-contain flex-shrink-0">
        <div class="text-sm text-slate">
            <p class="font-medium text-navy mb-1">Loopi's tip</p>
            <p>If this is a document you'll fill for different people or dates, save it as a template. Next time, Loopi will remember what stays the same and only ask for what changes.</p>
        </div>
    </div>

    {{-- What Loopi found --}}
    @php $summary = $template->variableSummary(); @endphp
    <div class="mt-6 bg-white rounded-2xl border border-line p-5">
        <h3 class="text-sm font-semibold text-navy mb-3 flex items-center gap-2">
            <x-icon name="sparkles" class="w-4 h-4 text-primary" />
            What Loopi found in "{{ $template->name }}"
        </h3>
        <div class="flex flex-wrap gap-2">
            @if($summary['total'] > 0)
                <span class="px-3 py-1 bg-primary/10 text-primary text-xs rounded-full font-medium">
                    {{ $summary['total'] }} fields detected
                </span>
            @endif
            @if(($summary['categories']['people'] ?? 0) > 0)
                <span class="px-3 py-1 bg-success/10 text-success text-xs rounded-full">
                    {{ $summary['categories']['people'] }} people
                </span>
            @endif
            @if(($summary['categories']['dates'] ?? 0) > 0)
                <span class="px-3 py-1 bg-warning/10 text-warning text-xs rounded-full">
                    {{ $summary['categories']['dates'] }} dates
                </span>
            @endif
            @if(($summary['categories']['amounts'] ?? 0) > 0)
                <span class="px-3 py-1 bg-warning/10 text-warning text-xs rounded-full">
                    {{ $summary['categories']['amounts'] }} amounts
                </span>
            @endif
            @if(($summary['categories']['organizations'] ?? 0) > 0)
                <span class="px-3 py-1 bg-slate/10 text-slate text-xs rounded-full">
                    {{ $summary['categories']['organizations'] }} organizations
                </span>
            @endif
        </div>
    </div>

</div>
</div>
</x-layouts.app>
