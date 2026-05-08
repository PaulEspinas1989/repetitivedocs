<x-layouts.app title="Upload Document — RepetitiveDocs">
<div class="p-4 md:p-8"
     x-data="{
         isDragging: false,
         fileName: '',
         templateName: '',
         documentType: '',
         handleDrop(e) {
             this.isDragging = false;
             const file = e.dataTransfer.files[0];
             if (!file) return;
             const allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
             if (!allowed.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) return;
             const dt = new DataTransfer();
             dt.items.add(file);
             document.getElementById('file-upload').files = dt.files;
             this.fileName = file.name;
             if (!this.templateName) this.templateName = file.name.replace(/\.[^/.]+$/, '');
         },
         handleFileSelect(e) {
             const file = e.target.files[0];
             if (!file) return;
             this.fileName = file.name;
             if (!this.templateName) this.templateName = file.name.replace(/\.[^/.]+$/, '');
         }
     }">

    <div class="max-w-5xl mx-auto">

        {{-- Page header --}}
        <div class="mb-6 md:mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-navy mb-2">Upload Your Document</h1>
            <p class="text-slate text-sm md:text-base">Upload a Word (.docx) file to preserve your exact formatting — fonts, tables, letterhead and all</p>
        </div>

        {{-- Errors --}}
        @if ($errors->any())
        <div class="mb-6 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
            <x-icon name="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid lg:grid-cols-3 gap-6 md:gap-8">

                {{-- ── Left: Upload form ──────────────────────────────── --}}
                <div class="lg:col-span-2 space-y-5">

                    {{-- Drop zone --}}
                    <div
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop($event)"
                        :class="{
                            'border-primary bg-blue-light': isDragging,
                            'border-success bg-success/5': !isDragging && fileName,
                            'border-line bg-white hover:border-primary hover:bg-blue-soft': !isDragging && !fileName
                        }"
                        class="border-2 border-dashed rounded-3xl p-8 md:p-12 text-center transition-all cursor-pointer"
                        @click="$refs.fileInput.click()"
                    >
                        <input
                            type="file"
                            id="file-upload"
                            name="document"
                            accept=".pdf,.doc,.docx"
                            class="hidden"
                            x-ref="fileInput"
                            @change="handleFileSelect($event)"
                        >

                        <div class="flex flex-col items-center">
                            {{-- File selected state --}}
                            <template x-if="fileName">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 md:w-20 md:h-20 bg-success/10 rounded-2xl flex items-center justify-center mb-4">
                                        <x-icon name="check-circle" class="w-8 h-8 md:w-10 md:h-10 text-success" />
                                    </div>
                                    <p class="text-base md:text-lg font-semibold text-navy mb-1" x-text="fileName"></p>
                                    <p class="text-sm text-slate">File selected — click to change</p>
                                </div>
                            </template>

                            {{-- Empty state --}}
                            <template x-if="!fileName">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center mb-4 transition-colors"
                                         :class="isDragging ? 'bg-primary/20' : 'bg-primary/10'">
                                        <x-icon name="upload" class="w-8 h-8 md:w-10 md:h-10 text-primary" />
                                    </div>
                                    <p class="text-base md:text-lg font-semibold text-navy mb-2">Drop your Word or PDF file here</p>
                                    <p class="text-sm text-slate mb-3">or click to browse</p>
                                    <p class="text-xs text-muted">DOCX recommended · PDF supported</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Smart file-type message --}}
                    <div x-show="!fileName"
                         class="flex items-start gap-3 p-3 bg-primary/5 border border-primary/20 rounded-xl text-sm">
                        <x-icon name="sparkles" class="w-4 h-4 text-primary flex-shrink-0 mt-0.5" />
                        <p class="text-slate">
                            <strong class="text-navy">Recommended: upload a .DOCX file.</strong> Word files preserve your exact formatting — fonts, tables, spacing, letterhead — in every generated document.
                        </p>
                    </div>

                    <div x-show="fileName && fileName.toLowerCase().endsWith('.pdf')" x-cloak
                         class="flex items-start gap-3 p-3 bg-warning/5 border border-warning/20 rounded-xl text-sm">
                        <x-icon name="alert-circle" class="w-4 h-4 text-warning flex-shrink-0 mt-0.5" />
                        <div class="text-slate">
                            <p class="font-medium text-navy mb-0.5">PDF uploaded — formatting note</p>
                            <p>AI will detect your variable fields, but the generated document will be a text-based replica — not a pixel-perfect copy of the original layout.</p>
                            <p class="mt-1 text-xs text-muted">For exact formatting, save your document as <strong>.docx</strong> from Word and upload that instead.</p>
                        </div>
                    </div>

                    <div x-show="fileName && (fileName.toLowerCase().endsWith('.docx') || fileName.toLowerCase().endsWith('.doc'))" x-cloak
                         class="flex items-start gap-3 p-3 bg-success/5 border border-success/20 rounded-xl text-sm">
                        <x-icon name="check-circle" class="w-4 h-4 text-success flex-shrink-0 mt-0.5" />
                        <p class="text-slate"><strong class="text-navy">DOCX detected.</strong> Your original formatting — fonts, tables, spacing, letterhead — will be preserved exactly in every generated document.</p>
                    </div>

                    {{-- Plan file size limits --}}
                    <div class="bg-blue-soft rounded-2xl p-4 border border-line">
                        <p class="text-sm font-medium text-navy mb-2">File Size Limits by Plan:</p>
                        <div class="grid grid-cols-2 gap-2 text-sm text-slate">
                            <div><span class="font-medium text-warning">Free:</span> 5 MB</div>
                            <div><span class="font-medium text-primary">Starter:</span> 25 MB</div>
                            <div><span class="font-medium text-primary">Pro:</span> 100 MB</div>
                            <div><span class="font-medium text-primary">Business:</span> 250 MB</div>
                        </div>
                        @php $planMb = $currentWorkspace?->plan?->file_size_limit_mb ?? 5; @endphp
                        <p class="text-xs text-muted mt-2">
                            Your plan: <span class="font-semibold text-navy">{{ $planMb }} MB limit</span>
                            @if($planMb <= 5)
                                — <a href="#pricing" class="text-primary hover:underline">Upgrade to upload larger files</a>
                            @endif
                        </p>
                    </div>

                    {{-- Template Name --}}
                    <div>
                        <label for="template_name" class="block text-sm font-medium text-navy mb-2">Template Name</label>
                        <input
                            type="text"
                            id="template_name"
                            name="template_name"
                            x-model="templateName"
                            value="{{ old('template_name') }}"
                            placeholder="e.g., Service Proposal Template"
                            class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('template_name') border-danger @enderror"
                        >
                    </div>

                    {{-- Document Type --}}
                    <div>
                        <label class="block text-sm font-medium text-navy mb-2">Document Type <span class="text-muted font-normal">(Optional)</span></label>
                        <input type="hidden" name="document_type" :value="documentType">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
                            @foreach(['Proposal','Contract','Certificate','HR Letter','Government Letter','Invoice','Other'] as $type)
                            <button
                                type="button"
                                @click="documentType = (documentType === '{{ $type }}' ? '' : '{{ $type }}')"
                                :class="documentType === '{{ $type }}'
                                    ? 'border-primary bg-blue-light text-primary font-medium'
                                    : 'border-line bg-white text-slate hover:border-primary/50'"
                                class="px-3 py-2 rounded-xl border-2 transition-all text-sm"
                            >
                                {{ $type }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Submit button --}}
                    <button
                        type="submit"
                        :disabled="!fileName || !templateName.trim()"
                        :class="(!fileName || !templateName.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-primary-dark'"
                        class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3.5 rounded-xl font-semibold text-sm transition-colors"
                    >
                        <x-icon name="sparkles" class="w-5 h-5" />
                        Analyze with AI
                    </button>

                </div>

                {{-- ── Right: Info cards ───────────────────────────── --}}
                <div class="space-y-5">

                    {{-- Loopi guide card --}}
                    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white">
                        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-28 h-28 object-contain mx-auto mb-4" style="mix-blend-mode:multiply;filter:brightness(0) invert(1)">
                        <h3 class="font-semibold mb-3 text-sm">What AI Will Do</h3>
                        <ul class="space-y-2 text-xs text-white/90">
                            @foreach(['Find names, dates, and amounts','Detect addresses and contact details','Identify repeated text patterns','Create editable template fields'] as $item)
                            <li class="flex items-start gap-2">
                                <x-icon name="check-circle" class="w-4 h-4 mt-0.5 flex-shrink-0" />
                                <span>{{ $item }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Tips card --}}
                    <div class="bg-white rounded-2xl p-6 border border-line">
                        <h3 class="font-semibold text-navy mb-3 text-sm flex items-center gap-2">
                            <x-icon name="file-text" class="w-5 h-5 text-primary" />
                            Tips for Best Results
                        </h3>
                        <ul class="space-y-2 text-sm text-slate">
                            <li>• Use a clean, well-formatted document</li>
                            <li>• Ensure text is selectable (not scanned images)</li>
                            <li>• Highlight variable content in your sample</li>
                            <li>• Include at least one complete example</li>
                        </ul>
                    </div>

                </div>

            </div>
        </form>
    </div>
</div>
</x-layouts.app>
