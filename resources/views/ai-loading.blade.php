<x-layouts.app title="Analyzing Document — RepetitiveDocs">
<div class="flex flex-col items-center justify-center min-h-[80vh] p-8 text-center"
     x-data="{
         step: 0,
         error: null,
         copyIndex: 0,
         copies: [
             'Loopi is finding the parts that usually change.',
             'Scanning for names, dates, and amounts.',
             'Looking for copy-paste traps.',
             'A few seconds here beats editing this manually later.',
             'Turning repeated words into reusable fields.',
             'Finding the tiny edits that usually eat your afternoon.',
             'Checking the tiny details humans usually miss.',
         ],
         get currentCopy() { return this.copies[this.copyIndex % this.copies.length]; },
         async startAnalysis() {
             // Animate through steps before calling API
             const delays = [600, 1600, 3200];
             for (let i = 0; i < delays.length; i++) {
                 await new Promise(r => setTimeout(r, delays[i]));
                 this.step = i + 1;
                 if (i < delays.length - 1) {
                     this.copyIndex++;
                 }
             }

             const csrfMeta = document.querySelector('meta[name=csrf-token]');
             if (!csrfMeta) {
                 this.error = 'Session error. Please refresh and try again.';
                 return;
             }

             const endpoint = '{{ route('documents.analyze', $document->id) }}';
             const headers  = {
                 'Content-Type': 'application/json',
                 'X-CSRF-TOKEN': csrfMeta.content,
                 'Accept': 'application/json',
             };

             // Rotate copy while waiting
             const copyInterval = setInterval(() => { this.copyIndex++; }, 3000);

             const maxRetries = 40;
             for (let attempt = 0; attempt < maxRetries; attempt++) {
                 try {
                     const res  = await fetch(endpoint, { method: 'POST', headers });
                     const data = await res.json();

                     if (data.success && data.redirect) {
                         clearInterval(copyInterval);
                         this.step = 4;
                         await new Promise(r => setTimeout(r, 600));
                         window.location.href = data.redirect;
                         return;
                     }

                     if (res.status === 202) {
                         await new Promise(r => setTimeout(r, 3000));
                         continue;
                     }

                     clearInterval(copyInterval);
                     this.error = data.message || 'Analysis failed. Please try uploading again.';
                     return;

                 } catch (e) {
                     clearInterval(copyInterval);
                     this.error = 'Network error. Please check your connection and try again.';
                     return;
                 }
             }

             clearInterval(copyInterval);
             this.error = 'This is taking longer than expected. Please try uploading again.';
         }
     }"
     x-init="startAnalysis()">

    {{-- Loopi + floating chips — wrapped in a fixed-size container so chips never overflow their bounds --}}
    <div class="relative mb-10 flex items-center justify-center" style="width:280px;height:200px;">

        {{-- Pulse glow ring behind Loopi --}}
        <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-40 h-40 rounded-full rd-pulse-glow"
             style="background:rgba(47,107,255,0.07);"></div>

        {{-- Loopi mascot --}}
        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi is analyzing"
             class="w-36 h-36 object-contain relative z-10 animate-float">

        {{-- Floating variable chips — positioned inside the 280×200 wrapper, no negative overflow --}}
        <div x-show="!error && step >= 1" x-cloak
             class="absolute top-2 right-0 px-3 py-1 bg-primary/10 text-primary text-xs font-mono rounded-full border border-primary/20 rd-float-chip-1 select-none whitespace-nowrap z-20">
            &#123;name&#125;
        </div>
        <div x-show="!error && step >= 2" x-cloak
             class="absolute top-16 left-0 px-3 py-1 bg-success/10 text-success text-xs font-mono rounded-full border border-success/20 rd-float-chip-2 select-none whitespace-nowrap z-20">
            &#123;date&#125;
        </div>
        <div x-show="!error && step >= 3" x-cloak
             class="absolute bottom-4 right-4 px-3 py-1 bg-warning/10 text-warning text-xs font-mono rounded-full border border-warning/20 rd-float-chip-3 select-none whitespace-nowrap z-20">
            &#123;amount&#125;
        </div>
    </div>

    {{-- Error state --}}
    <div x-show="error" x-cloak class="max-w-sm w-full mb-6">
        <div class="flex items-start gap-3 p-4 bg-danger/10 border border-danger/20 rounded-2xl text-sm text-danger text-left mb-4">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <div>
                <p class="font-medium mb-1">Analysis didn't complete</p>
                <p x-text="error" class="text-xs opacity-80"></p>
            </div>
        </div>
        <a href="{{ route('upload') }}"
           class="flex items-center justify-center gap-2 w-full bg-primary text-white py-3 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Try uploading again
        </a>
    </div>

    {{-- Processing state --}}
    <div x-show="!error" class="w-full max-w-md">
        <h1 class="text-2xl font-bold text-navy mb-2">Loopi is analyzing your document</h1>
        <p class="text-slate text-sm mb-1 min-h-[1.25rem] transition-all duration-500" x-text="currentCopy"></p>
        <p class="text-xs text-muted mb-8">This usually takes 10–30 seconds.</p>

        {{-- Progress checklist --}}
        <div class="space-y-3 text-left mb-8" aria-live="polite">
            @php
                $steps = [
                    ['label' => 'Reading your document structure',    'sub' => 'Understanding layout and content'],
                    ['label' => 'Detecting variable fields',          'sub' => 'Finding names, dates, amounts, and IDs'],
                    ['label' => 'Identifying repeated patterns',      'sub' => 'Spotting text that changes across copies'],
                    ['label' => 'Building your automation map',       'sub' => 'Turning fields into reusable variables'],
                ];
            @endphp

            @foreach ($steps as $i => $s)
            <div class="flex items-start gap-3 bg-white rounded-xl px-4 py-3 border border-line transition-all duration-300"
                 :class="step > {{ $i }} ? 'border-success/30 bg-success/5' : (step === {{ $i }} ? 'border-primary/30 bg-primary/5' : '')">

                {{-- Done checkmark --}}
                <div x-show="step > {{ $i }}" class="w-5 h-5 rounded-full bg-success flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                </div>

                {{-- Active spinner --}}
                <div x-show="step === {{ $i }}" class="flex-shrink-0 mt-0.5">
                    <x-spinner class="text-primary" />
                </div>

                {{-- Pending dot --}}
                <div x-show="step < {{ $i }}" class="w-5 h-5 rounded-full border-2 border-line flex-shrink-0 mt-0.5"></div>

                <div class="min-w-0">
                    <p class="text-sm font-medium leading-tight"
                       :class="step > {{ $i }} ? 'text-success' : (step === {{ $i }} ? 'text-primary' : 'text-muted')">
                        {{ $s['label'] }}
                    </p>
                    <p class="text-xs text-muted mt-0.5" x-show="step === {{ $i }}">{{ $s['sub'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Fun sub-copy --}}
        <p class="text-xs text-muted italic">
            "A few seconds here beats editing each file one by one."
        </p>
    </div>

</div>
</x-layouts.app>
