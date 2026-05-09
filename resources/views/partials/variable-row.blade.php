@php
    $approveUrl = route('templates.variables.approve', [$template->id, $var->id]);
    $rejectUrl  = route('templates.variables.reject',  [$template->id, $var->id]);
    $undoUrl    = route('templates.variables.undo',    [$template->id, $var->id]);
@endphp
{{-- Compact inline approve/reject row — no page reload, no scroll jump --}}
<div class="flex items-center gap-3 px-5 py-3.5 {{ !$loop->last ? 'border-b border-line' : '' }}"
     x-data='{
         status:  @json($var->approval_status),
         loading: null,
         csrf() { return document.querySelector("meta[name=csrf-token]")?.content ?? ""; },
         async doAction(action, url) {
             if (this.loading) return;
             this.loading = action;
             try {
                 const res  = await fetch(url, {
                     method: "POST",
                     headers: { "Accept": "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": this.csrf() },
                 });
                 const data = await res.json();
                 if (!res.ok || !data.success) throw new Error(data.message ?? "Failed");
                 this.status = data.status;
                 this.$dispatch("rd-status-change", { to: data.status, counts: data.counts, readiness: data.readiness, label: data.label });
             } catch (e) {
                 if (typeof window.rdToast === "function") window.rdToast("Could not update field. Please try again.", "error");
             } finally { this.loading = null; }
         },
         approve() { this.doAction("approving", @json($approveUrl)); },
         reject()  { this.doAction("rejecting", @json($rejectUrl));  },
         undo()    { this.doAction("undoing",   @json($undoUrl));    },
     }">

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

    {{-- Inline status + action buttons — no form POST --}}
    <div class="flex items-center gap-2 flex-shrink-0">

        {{-- Loading spinner --}}
        <template x-if="loading">
            <div class="flex items-center gap-1.5 px-3 py-1.5 text-xs text-muted">
                <x-spinner size="xs" />
                <span x-text="loading === 'approving' ? 'Approving…' : (loading === 'rejecting' ? 'Rejecting…' : 'Updating…')"></span>
            </div>
        </template>

        {{-- PENDING --}}
        <template x-if="!loading && status === 'pending'">
            <div class="flex items-center gap-2">
                <button @click="approve()"
                        class="flex items-center gap-1 px-3 py-1.5 bg-success text-white text-xs font-medium rounded-lg hover:bg-green-600 transition-colors"
                        aria-label="Approve {{ $var->label }}">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" /> Approve
                </button>
                <button @click="reject()"
                        class="flex items-center gap-1 px-3 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors"
                        aria-label="Reject {{ $var->label }}">
                    <x-icon name="x" class="w-3.5 h-3.5" /> Reject
                </button>
            </div>
        </template>

        {{-- APPROVED --}}
        <template x-if="!loading && status === 'approved'">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-success/10 text-success" aria-live="polite">
                    Approved
                </span>
                <button @click="reject()"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-danger/10 text-danger text-xs font-medium rounded-lg hover:bg-danger/20 transition-colors"
                        aria-label="Reject {{ $var->label }}">
                    <x-icon name="x" class="w-3.5 h-3.5" />
                </button>
                <button @click="undo()"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-soft text-slate text-xs font-medium rounded-lg hover:bg-blue-light transition-colors"
                        aria-label="Undo approval of {{ $var->label }}" title="Move back to pending">
                    <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                </button>
            </div>
        </template>

        {{-- REJECTED --}}
        <template x-if="!loading && status === 'rejected'">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-danger/10 text-danger" aria-live="polite">
                    Rejected
                </span>
                <button @click="approve()"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-success/10 text-success text-xs font-medium rounded-lg hover:bg-success/20 transition-colors"
                        aria-label="Approve {{ $var->label }}" title="Approve this field">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                </button>
                <button @click="undo()"
                        class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-soft text-slate text-xs font-medium rounded-lg hover:bg-blue-light transition-colors"
                        aria-label="Undo rejection of {{ $var->label }}" title="Move back to pending">
                    <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                </button>
            </div>
        </template>

    </div>
</div>
