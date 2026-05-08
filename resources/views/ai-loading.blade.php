<x-layouts.app title="Analyzing Document — RepetitiveDocs">
<div class="flex flex-col items-center justify-center min-h-[70vh] p-8 text-center"
     x-data="{
         step: 0,
         error: null,
         async startAnalysis() {
             // Animate through steps
             const steps = [500, 1500, 3000];
             for (let i = 0; i < steps.length; i++) {
                 await new Promise(r => setTimeout(r, steps[i]));
                 this.step = i + 1;
             }

             const csrfMeta = document.querySelector('meta[name=csrf-token]');
             if (!csrfMeta) {
                 this.error = 'Session error. Please refresh the page and try again.';
                 return;
             }

             const endpoint = '{{ route('documents.analyze', $document->id) }}';
             const headers  = {
                 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': csrfMeta.content,
                 'Accept': 'application/json',
             };

             // Retry loop — handles 202 (still processing) gracefully
             const maxRetries = 40;
             for (let attempt = 0; attempt < maxRetries; attempt++) {
                 try {
                     const res  = await fetch(endpoint, { method: 'POST', headers });
                     const data = await res.json();

                     if (data.success && data.redirect) {
                         this.step = 4;
                         await new Promise(r => setTimeout(r, 500));
                         window.location.href = data.redirect;
                         return;
                     }

                     if (res.status === 202) {
                         // Still processing — wait 3 seconds and retry
                         await new Promise(r => setTimeout(r, 3000));
                         continue;
                     }

                     // Server returned an error
                     this.error = data.message || 'Analysis failed. Please try again.';
                     return;

                 } catch (e) {
                     this.error = 'Network error. Please check your connection and try again.';
                     return;
                 }
             }

             this.error = 'Analysis is taking too long. Please try uploading again.';
         }
     }"
     x-init="startAnalysis()">

    <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
         class="w-40 h-40 object-contain mb-8 animate-float">

    <h1 class="text-2xl font-bold text-navy mb-3">Loopi is analyzing your document</h1>
    <p class="text-slate text-sm max-w-sm mb-8">
        Finding variable fields, detecting patterns, and building your automation map.
    </p>

    {{-- Error state --}}
    <div x-show="error" x-cloak class="mb-6 max-w-sm w-full flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-danger text-left">
        <x-icon name="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
        <div>
            <p x-text="error"></p>
            <a href="{{ route('upload') }}" class="underline mt-1 inline-block">Try uploading again</a>
        </div>
    </div>

    {{-- Progress checklist --}}
    <div x-show="!error" class="w-full max-w-sm space-y-3 text-left">
        @php
            $steps = [
                'Reading document structure',
                'Detecting variable fields',
                'Identifying repeated patterns',
                'Building automation map',
            ];
        @endphp

        @foreach ($steps as $i => $label)
        <div class="flex items-center gap-3 bg-white rounded-xl px-4 py-3 border border-line">
            {{-- Done --}}
            <div x-show="step > {{ $i }}" class="w-5 h-5 rounded-full bg-success flex items-center justify-center flex-shrink-0">
                <x-icon name="check-circle" class="w-4 h-4 text-white" />
            </div>
            {{-- Active spinner --}}
            <div x-show="step === {{ $i }}" class="w-5 h-5 rounded-full border-2 border-primary border-t-transparent animate-spin flex-shrink-0"></div>
            {{-- Pending --}}
            <div x-show="step < {{ $i }}" class="w-5 h-5 rounded-full border-2 border-line flex-shrink-0"></div>

            <span class="text-sm"
                  :class="{
                      'text-navy font-medium': step > {{ $i }},
                      'text-primary font-medium': step === {{ $i }},
                      'text-muted': step < {{ $i }}
                  }">
                {{ $label }}
            </span>
        </div>
        @endforeach
    </div>

</div>
</x-layouts.app>
