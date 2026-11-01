@php
    $flashSuccess = session('success');
    $flashError = session('error');
    $flashInfo = session('info') ?? session('status');
    $flashWarning = session('warning');
@endphp

<div
    x-data="{
        messages: [],
        init() {
            @if($flashSuccess)
                this.add('{{ addslashes($flashSuccess) }}', 'success');
            @endif
            @if($flashError)
                this.add('{{ addslashes($flashError) }}', 'error');
            @endif
            @if($flashInfo)
                this.add('{{ addslashes($flashInfo) }}', 'info');
            @endif
            @if($flashWarning)
                this.add('{{ addslashes($flashWarning) }}', 'warning');
            @endif
        },
        add(text, type = 'info', duration = 4000) {
            const id = Date.now() + Math.random().toString(36).substring(2, 5);
            this.messages.push({ id, text, type });
            if (duration > 0) {
                setTimeout(() => this.remove(id), duration);
            }
        },
        remove(id) {
            this.messages = this.messages.filter(m => m.id !== id);
        }
    }"
    @toast.window="add($event.detail.message || $event.detail.text || $event.detail, $event.detail.type || 'info', $event.detail.duration || 4000)"
    class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none px-4 sm:px-0"
    aria-live="polite">
    <template x-for="msg in messages" :key="msg.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="pointer-events-auto rounded-xl p-3 shadow-md border flex items-center justify-between gap-3 text-xs font-medium"
            :class="{
                'bg-emerald-50 text-emerald-900 border-emerald-200': msg.type === 'success',
                'bg-rose-50 text-rose-900 border-rose-200': msg.type === 'error',
                'bg-amber-50 text-amber-900 border-amber-200': msg.type === 'warning',
                'bg-surface-container-lowest text-on-surface border-outline-variant': msg.type === 'info'
            }">
            <div class="flex items-center gap-2 min-w-0">
                <span
                    class="material-symbols-outlined text-[18px] shrink-0"
                    :class="{
                        'text-emerald-600': msg.type === 'success',
                        'text-rose-600': msg.type === 'error',
                        'text-amber-600': msg.type === 'warning',
                        'text-primary': msg.type === 'info'
                    }"
                    x-text="msg.type === 'success' ? 'check_circle' : (msg.type === 'error' ? 'error' : (msg.type === 'warning' ? 'warning' : 'info'))">
                </span>
                <span class="truncate" x-text="msg.text"></span>
            </div>
            <button
                type="button"
                @click="remove(msg.id)"
                class="p-1 rounded-md text-on-surface-variant hover:text-on-surface hover:bg-black/5 shrink-0"
                aria-label="Dismiss toast">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    </template>
</div>

