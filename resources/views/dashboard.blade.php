<x-layouts.app title="Dashboard — RepetitiveDocs">
<div class="p-6 lg:p-8">

    {{-- Page header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-navy">Dashboard</h1>
        <p class="text-slate text-sm mt-1">Welcome to RepetitiveDocs. Upload a document to get started.</p>
    </div>

    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-40 h-40 object-contain mb-6">
        <h2 class="text-xl font-bold text-navy mb-2">Upload your first document</h2>
        <p class="text-slate text-sm max-w-sm mb-6">
            Loopi will scan it, detect the fields that change, and turn it into a reusable template — in seconds.
        </p>
        @if (Route::has('upload'))
        <a href="{{ route('upload') }}" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors">
            <x-icon name="upload" class="w-5 h-5" />
            Upload a Document
        </a>
        @endif
    </div>

</div>
</x-layouts.app>
