@props([])

<div
    x-data="{
        notifications: [],
        add(notice) {
            const id = Date.now() + Math.random().toString(36).substring(2, 9);
            const notification = {
                id,
                type: notice.type || 'info', // 'success' | 'error' | 'warning' | 'info'
                message: notice.message || notice,
                title: notice.title || null,
                duration: notice.duration || 4500
            };
            this.notifications.push(notification);

            if (notification.duration > 0) {
                setTimeout(() => {
                    this.remove(id);
                }, notification.duration);
            }
        },
        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }"
    @notify.window="add($event.detail)"
    x-init="
        @if (session('success'))
            add({ type: 'success', message: @js(session('success')) });
        @endif
        @if (session('status'))
            add({ type: 'info', message: @js(session('status')) });
        @endif
        @if (session('error'))
            add({ type: 'error', message: @js(session('error')) });
        @endif
        @if (session('warning'))
            add({ type: 'warning', message: @js(session('warning')) });
        @endif
    "
    class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0">

    <template x-for="item in notifications" :key="item.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="pointer-events-auto rounded-2xl p-4 shadow-xl border backdrop-blur-md flex items-start gap-3 transition-all"
            :class="{
                'bg-surface-container-lowest/95 text-on-surface border-emerald-500/40 ring-1 ring-emerald-500/10': item.type === 'success',
                'bg-surface-container-lowest/95 text-on-surface border-rose-500/40 ring-1 ring-rose-500/10': item.type === 'error',
                'bg-surface-container-lowest/95 text-on-surface border-amber-500/40 ring-1 ring-amber-500/10': item.type === 'warning',
                'bg-surface-container-lowest/95 text-on-surface border-primary/40 ring-1 ring-primary/10': item.type === 'info'
            }">

            {{-- Icon --}}
            <div class="flex-shrink-0 mt-0.5">
                <template x-if="item.type === 'success'">
                    <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px] font-bold">check</span>
                    </span>
                </template>
                <template x-if="item.type === 'error'">
                    <span class="w-6 h-6 rounded-full bg-rose-500/15 text-rose-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px] font-bold">close</span>
                    </span>
                </template>
                <template x-if="item.type === 'warning'">
                    <span class="w-6 h-6 rounded-full bg-amber-500/15 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px] font-bold">priority_high</span>
                    </span>
                </template>
                <template x-if="item.type === 'info'">
                    <span class="w-6 h-6 rounded-full bg-primary/15 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[16px] font-bold">info</span>
                    </span>
                </template>
            </div>

            {{-- Text Body --}}
            <div class="flex-1 min-w-0">
                <template x-if="item.title">
                    <h5 class="text-xs font-bold text-on-surface mb-0.5" x-text="item.title"></h5>
                </template>
                <p class="text-xs text-on-surface-variant font-medium leading-relaxed" x-text="item.message"></p>
            </div>

            {{-- Close Button --}}
            <button
                type="button"
                @click="remove(item.id)"
                class="flex-shrink-0 text-outline-variant hover:text-on-surface transition-colors p-0.5"
                aria-label="Dismiss">
                <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
        </div>
    </template>
</div>

