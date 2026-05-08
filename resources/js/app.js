import Alpine from 'alpinejs';

window.Alpine = Alpine;

// ─── Loading copy banks — randomly rotated ──────────────────
const RD_COPY = {
    ai: [
        "Loopi is finding the parts that usually change…",
        "Scanning for names, dates, and amounts…",
        "Looking for copy-paste traps…",
        "A few seconds here beats editing this manually later.",
        "Turning repeated words into reusable fields…",
        "Finding the tiny edits that usually eat your afternoon…",
        "Loopi is spotting the fields you shouldn't have to retype.",
        "Checking the tiny details humans usually miss…",
    ],
    generate: [
        "Turning your answers into a finished document…",
        "No copy-paste. Just generating.",
        "Your repetitive document is becoming a finished copy.",
        "Loopi is assembling your document.",
        "A few seconds here beats editing this line by line.",
        "Making one clean document from all your answers.",
    ],
    upload: [
        "Preparing for Loopi…",
        "One upload now, fewer repetitive edits later.",
        "Reading your file so you can stop rewriting it.",
        "A few seconds here can save hours of copy-paste later.",
        "Bringing it in…",
    ],
    save: [
        "Saving…",
        "Locking it in…",
        "Keeping it set…",
    ],
};

window.rdCopy = (bank) => {
    const list = RD_COPY[bank] || RD_COPY.save;
    return list[Math.floor(Math.random() * list.length)];
};

// ─── Global top loading bar ──────────────────────────────────
const rdBar = {
    el: null,
    init() {
        this.el = document.getElementById('rd-loading-bar');
    },
    show() {
        if (this.el) this.el.classList.add('active');
    },
    hide() {
        if (this.el) {
            this.el.classList.remove('active');
        }
    },
};
document.addEventListener('DOMContentLoaded', () => rdBar.init());

// ─── Global form auto-loading state ─────────────────────────
// Any form submit automatically shows loading on its submit button.
// Add data-no-loading to opt out.
// Add data-loading-text="Saving…" for custom loading text.
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.noLoading !== undefined) return;

    const btn = form.querySelector('button[type="submit"]:not([data-no-loading]), input[type="submit"]:not([data-no-loading])');
    if (!btn || btn.disabled) return;

    // Determine loading text
    const customText = btn.dataset.loadingText;
    const displayText = customText || deriveLoadingText(btn);

    btn.dataset.originalHtml = btn.innerHTML;
    btn.setAttribute('disabled', 'disabled');
    btn.classList.add('rd-btn-loading');
    btn.innerHTML = spinnerHtml(displayText);

    rdBar.show();
});

function deriveLoadingText(btn) {
    const text = btn.textContent.trim();
    // Map common button labels to loading copy
    const map = {
        'upload': 'Uploading…',
        'analyze': 'Scanning…',
        'generate': 'Generating…',
        'save': 'Saving…',
        'submit': 'Submitting…',
        'send': 'Sending…',
        'publish': 'Publishing…',
        'create': 'Creating…',
        'login': 'Signing in…',
        'sign in': 'Signing in…',
        'register': 'Creating account…',
        'continue': 'Loading…',
        'approve': 'Approving…',
        'reject': 'Rejecting…',
        'accept': 'Accepting…',
        'retry': 'Retrying…',
        'reset': 'Resetting…',
        'update': 'Updating…',
        'connect': 'Connecting…',
        'invite': 'Sending invite…',
        'upgrade': 'Upgrading…',
        'download': 'Preparing…',
    };
    const lc = text.toLowerCase();
    for (const [key, val] of Object.entries(map)) {
        if (lc.includes(key)) return val;
    }
    return text.length < 25 ? text + '…' : 'Processing…';
}

function spinnerHtml(text) {
    return `<span class="flex items-center justify-center gap-2">
        <span class="rd-spinner" aria-hidden="true"></span>
        <span>${text}</span>
    </span>`;
}

// ─── Global toast system ─────────────────────────────────────
window.rdToast = function (message, type = 'success', duration = 4000) {
    const container = document.getElementById('rd-toast-container');
    if (!container) return;

    const icons = {
        success: `<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>`,
        error:   `<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>`,
        info:    `<svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>`,
    };
    const colors = {
        success: 'bg-success/10 border-success/30 text-success',
        error:   'bg-danger/10 border-danger/30 text-danger',
        info:    'bg-primary/10 border-primary/30 text-primary',
    };

    const toast = document.createElement('div');
    toast.className = `rd-slide-up flex items-center gap-3 px-4 py-3 rounded-xl border text-sm font-medium shadow-lg min-w-64 max-w-sm ${colors[type] || colors.success}`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = `${icons[type] || icons.success}<span>${message}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
};

// ─── Alpine magic: $loading ───────────────────────────────────
// Usage: x-data="{ loading: false }"  then  @click="loading = true"
// Buttons can use :disabled="loading" and show spinner with x-show

// ─── Alpine component: rdBtn ──────────────────────────────────
// Registers a reusable loading-button Alpine component
document.addEventListener('alpine:init', () => {

    Alpine.data('rdBtn', (options = {}) => ({
        loading: false,
        done: false,
        text: options.text || 'Submit',
        loadingText: options.loadingText || 'Processing…',
        doneText: options.doneText || 'Done',
        async trigger(action) {
            if (this.loading) return;
            this.loading = true;
            try {
                await action();
                this.done = true;
                setTimeout(() => { this.done = false; this.loading = false; }, 2000);
            } catch {
                this.loading = false;
            }
        },
    }));

    // Skeleton visibility helper
    Alpine.data('skeletonSection', () => ({
        loaded: false,
        init() {
            // Mark loaded after first paint
            requestAnimationFrame(() => {
                setTimeout(() => { this.loaded = true; }, 150);
            });
        },
    }));
});

Alpine.start();
