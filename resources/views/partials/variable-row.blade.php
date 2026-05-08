{{-- Compact approve/reject/undo row — used in automation-map grouped lists --}}
<div class="flex items-center gap-3 px-5 py-3.5 {{ !$loop->last ? 'border-b border-line' : '' }}">

    {{-- Type badge --}}
    <span class="px-2.5 py-1 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }} flex-shrink-0">
        {{ $var->type }}
    </span>

    {{-- Label + example --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
            <p class="text-sm font-medium text-navy truncate">{{ $var->label }}</p>
            @if(($var->occurrences ?: 1) > 1)
            <span class="px-1.5 py-0.5 bg-primary/10 text-primary text-xs rounded-full flex-shrink-0">
                ×{{ $var->occurrences }}
            </span>
            @endif
        </div>
        @if($var->example_value)
        <p class="text-xs text-muted truncate">e.g. {{ $var->example_value }}</p>
        @endif
    </div>

    {{-- Status + action buttons --}}
    <div class="flex items-center gap-2 flex-shrink-0">

        @if($var->approval_status === 'pending')
            <form method="POST" action="{{ route('templates.variables.approve', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-3 py-1.5 bg-success text-white text-xs font-medium rounded-lg hover:bg-green-600 transition-colors">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                    Approve
                </button>
            </form>
            <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-3 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                    Reject
                </button>
            </form>

        @elseif($var->approval_status === 'approved')
            <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-success/10 text-success">
                Approved
            </span>
            <form method="POST" action="{{ route('templates.variables.reject', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors"
                        title="Reject">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                </button>
            </form>
            <form method="POST" action="{{ route('templates.variables.undo', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-soft text-slate text-xs font-medium rounded-lg hover:bg-blue-light transition-colors"
                        title="Move back to pending">
                    <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                </button>
            </form>

        @elseif($var->approval_status === 'rejected')
            <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-danger/10 text-danger">
                Rejected
            </span>
            <form method="POST" action="{{ route('templates.variables.approve', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-success/10 text-success text-xs font-medium rounded-lg hover:bg-success/20 transition-colors"
                        title="Approve">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                </button>
            </form>
            <form method="POST" action="{{ route('templates.variables.undo', [$template->id, $var->id]) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-soft text-slate text-xs font-medium rounded-lg hover:bg-blue-light transition-colors"
                        title="Move back to pending">
                    <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                </button>
            </form>
        @endif

    </div>
</div>
