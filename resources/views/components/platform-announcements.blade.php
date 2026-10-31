@php
    $user = auth()->user();
    $role = $user?->role;

    $announcements = \App\Models\Announcement::where(function ($q) use ($role) {
            $q->whereNull('target_roles')
              ->orWhereJsonContains('target_roles', $role);
        })
        ->where(function ($q) {
            $q->whereNull('published_at')
              ->orWhere('published_at', '<=', now());
        })
        ->latest('published_at')
        ->limit(3)
        ->get();
@endphp

@if($announcements->isNotEmpty())
    <div class="space-y-3 mb-6">
        @foreach($announcements as $announcement)
            <div class="bg-primary/5 border border-primary/20 rounded-2xl p-4 flex items-start gap-3 shadow-xs">
                <span class="material-symbols-outlined text-primary text-[22px] flex-shrink-0 mt-0.5">campaign</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="font-bold text-xs text-on-surface">{{ $announcement->title }}</h4>
                        <span class="text-[10px] text-on-surface-variant whitespace-nowrap">
                            {{ $announcement->published_at?->diffForHumans() ?? $announcement->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-1 leading-relaxed">{{ $announcement->body }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
