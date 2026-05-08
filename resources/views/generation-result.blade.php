<x-layouts.app title="Document Ready — RepetitiveDocs">
<div class="p-6 lg:p-8">
<div class="max-w-5xl mx-auto">

    {{-- Loopi success --}}
    <div class="flex justify-center mb-8">
        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-40 h-40 object-contain animate-float">
    </div>

    {{-- Header --}}
    <div class="text-center mb-10">
        <h1 class="text-3xl lg:text-4xl font-bold text-navy mb-3">Your document is ready!</h1>
        <p class="text-lg text-slate">Loopi has successfully generated your personalized document</p>
    </div>

    <div class="grid lg:grid-cols-3 gap-8">

        {{-- ── Document preview ────────────────────────────────── --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-line p-6">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-lg font-semibold text-navy">Document Summary</h2>
                    <span class="px-3 py-1 bg-success/10 text-success rounded-full text-xs font-semibold flex items-center gap-1">
                        <x-icon name="check-circle" class="w-3.5 h-3.5" /> Ready
                    </span>
                </div>

                {{-- Field values summary --}}
                <div class="bg-canvas rounded-xl p-5">
                    <h3 class="text-sm font-semibold text-navy mb-4">{{ $generated->template->name }}</h3>
                    <div class="space-y-3">
                        @foreach($generated->template->approvedVariables as $var)
                        @php $value = $generated->variable_values[$var->name] ?? '—'; @endphp
                        <div class="flex items-start gap-3 bg-white rounded-xl px-4 py-3 border border-line">
                            <span class="px-2 py-0.5 rounded text-xs {{ $var->typeBadgeColor() }} flex-shrink-0 mt-0.5">{{ $var->type }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-muted">{{ $var->label }}</p>
                                <p class="text-sm font-medium text-navy truncate">{{ $value ?: '—' }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Actions ─────────────────────────────────────────── --}}
        <div class="space-y-4">

            {{-- Download --}}
            @php
                $isDocx = str_ends_with(strtolower($generated->file_name ?? ''), '.docx');
                $downloadLabel = $isDocx ? 'Download Word Document' : 'Download PDF';
            @endphp
            <a href="{{ route('generated-documents.download', $generated->id) }}"
               class="flex items-center justify-center gap-3 w-full bg-primary text-white py-4 rounded-2xl font-semibold hover:bg-primary-dark transition-colors">
                <x-icon name="download" class="w-5 h-5" />
                {{ $downloadLabel }}
            </a>

            {{-- Generate another --}}
            <a href="{{ route('fillable-form', $generated->template_id) }}"
               class="flex items-center justify-center gap-3 w-full border-2 border-line text-navy py-4 rounded-2xl font-semibold hover:border-primary hover:bg-blue-soft transition-all">
                <x-icon name="refresh" class="w-5 h-5" />
                Generate Another
            </a>

            {{-- Back to editor --}}
            <a href="{{ route('templates.editor', $generated->template_id) }}"
               class="flex items-center justify-center gap-3 w-full text-slate py-3 text-sm hover:text-navy transition-colors">
                <x-icon name="arrow-left" class="w-4 h-4" />
                Back to Template Editor
            </a>

            {{-- Document info --}}
            <div class="bg-white rounded-2xl border border-line p-5">
                <h3 class="text-sm font-semibold text-navy mb-3">Document Details</h3>
                <div class="space-y-2 text-sm text-slate">
                    <div class="flex justify-between">
                        <span>Template:</span>
                        <span class="font-medium text-navy truncate ml-2">{{ $generated->template->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Generated:</span>
                        <span class="font-medium text-navy">{{ $generated->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>File:</span>
                        <span class="font-medium text-navy text-xs truncate ml-2">{{ $generated->file_name }}</span>
                    </div>
                </div>
            </div>

                {{-- Fixed fields review CTA (shown after first generation from a saved template) --}}
            @if($generated->template->is_saved_template && !$generated->template->fixed_fields_reviewed)
            <div class="bg-gradient-to-br from-success to-green-600 rounded-2xl p-5 text-white">
                <div class="flex items-start gap-3">
                    <x-icon name="sparkles" class="w-6 h-6 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-bold mb-1">Make the next one even faster</p>
                        <p class="text-xs text-white/90 mb-3">Save answers that stay the same — Loopi will fill them automatically next time.</p>
                        <a href="{{ route('fixed-fields.review', ['template' => $generated->template_id, 'generated' => $generated->id]) }}"
                           class="block text-center bg-white text-success py-2 rounded-xl text-sm font-semibold hover:bg-green-50 transition-colors">
                            Review saved answers →
                        </a>
                    </div>
                </div>
            </div>
            @elseif(!$generated->template->is_saved_template)
            {{-- One-time generation: offer to save as template --}}
            <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-5 text-white">
                <div class="flex items-start gap-3">
                    <x-icon name="layers" class="w-6 h-6 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-bold mb-1">Want to reuse this document?</p>
                        <p class="text-xs text-white/90 mb-3">Save it as a template so Loopi can remember what stays the same next time.</p>
                        <a href="{{ route('fixed-fields.save-as-template', $generated->template_id) }}"
                           class="block text-center bg-white text-primary py-2 rounded-xl text-sm font-semibold hover:bg-blue-soft transition-colors">
                            Save as Template →
                        </a>
                    </div>
                </div>
            </div>
            @else
            {{-- Upload next doc CTA --}}
            <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-5 text-white text-center">
                <x-icon name="upload" class="w-8 h-8 mx-auto mb-3 opacity-90" />
                <p class="text-sm font-semibold mb-1">Have another document?</p>
                <p class="text-xs text-white/80 mb-3">Upload it and let Loopi automate it too</p>
                <a href="{{ route('upload') }}" class="block bg-white text-primary py-2 rounded-xl text-sm font-semibold hover:bg-blue-soft transition-colors">
                    Upload Document
                </a>
            </div>
            @endif

        </div>
    </div>

</div>
</div>
</x-layouts.app>
