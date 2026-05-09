<x-layouts.app title="Dashboard — RepetitiveDocs">
<div class="p-6 lg:p-8">

    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-navy">My Templates</h1>
            <p class="text-slate text-sm mt-1">Upload a document to create a reusable template.</p>
        </div>
        <a href="{{ route('upload') }}"
           class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors">
            <x-icon name="upload" class="w-4 h-4" />
            Upload Document
        </a>
    </div>

    @if(session('toast'))
    <div class="mb-6 p-4 bg-success/10 border border-success/20 rounded-xl text-sm text-success flex items-center gap-2">
        <x-icon name="check-circle" class="w-4 h-4" />
        {{ session('toast') }}
    </div>
    @endif

    @if($templates->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-center">
        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-40 h-40 object-contain mb-6">
        <h2 class="text-xl font-bold text-navy mb-2">Upload your first document</h2>
        <p class="text-slate text-sm max-w-sm mb-6">
            Loopi will scan it, detect the fields that change, and turn it into a reusable template — in seconds.
        </p>
        <a href="{{ route('upload') }}"
           class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors">
            <x-icon name="upload" class="w-5 h-5" />
            Upload a Document
        </a>
    </div>
    @else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($templates as $template)
        <div class="bg-white rounded-2xl border border-line p-5 flex flex-col gap-4 hover:border-primary/30 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-semibold text-navy truncate">{{ $template->name }}</h3>
                    <p class="text-xs text-muted mt-0.5">
                        {{ $template->document_type ?: 'Document' }} ·
                        {{ $template->approved_variables_count }} approved field{{ $template->approved_variables_count === 1 ? '' : 's' }}
                    </p>
                </div>
                <span class="px-2 py-0.5 rounded-lg text-xs font-medium flex-shrink-0
                    {{ $template->status === 'draft' ? 'bg-warning/10 text-warning' : 'bg-success/10 text-success' }}">
                    {{ ucfirst($template->status) }}
                </span>
            </div>

            <p class="text-xs text-muted">
                Created {{ $template->created_at->diffForHumans() }}
            </p>

            <div class="flex gap-2 pt-1 border-t border-line">
                <a href="{{ route('templates.editor', $template->id) }}"
                   class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-primary/10 text-primary rounded-xl text-xs font-medium hover:bg-primary/20 transition-colors">
                    <x-icon name="settings" class="w-3.5 h-3.5" />
                    Editor
                </a>
                @if($template->approved_variables_count > 0)
                <a href="{{ route('fillable-form', $template->id) }}"
                   class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-success/10 text-success rounded-xl text-xs font-medium hover:bg-success/20 transition-colors">
                    <x-icon name="sparkles" class="w-3.5 h-3.5" />
                    Generate
                </a>
                @endif
                <form method="POST" action="{{ route('templates.destroy', $template->id) }}"
                      x-data
                      @submit.prevent="if(confirm('Delete this template? This cannot be undone.')) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="flex items-center justify-center p-2 bg-danger/10 text-danger rounded-xl hover:bg-danger/20 transition-colors"
                            title="Delete template">
                        <x-icon name="trash" class="w-3.5 h-3.5" />
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
</x-layouts.app>
