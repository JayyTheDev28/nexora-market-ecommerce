@extends('layouts.app')

@section('title', 'Platform Settings & Announcements — Admin Portal')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ announcementModalOpen: false }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-admin-nav active="settings" />

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4">
                <ul class="list-disc list-inside text-xs text-error font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Platform Configuration & Broadcasts</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Manage global marketplace parameters, commission take-rates, return policies, and broadcast announcements.</p>
            </div>

            <button type="button" @click="announcementModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                <span class="material-symbols-outlined text-[18px]">campaign</span>
                <span>Post Announcement</span>
            </button>
        </div>

        {{-- Grid: Platform Settings & Announcements --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            {{-- Left Column: Global Platform Settings --}}
            <div class="lg:col-span-1 bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">tune</span>
                    <span>Platform Global Settings</span>
                </h2>

                <form action="{{ route('admin.settings.platform.update') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Marketplace Platform Name</label>
                        <input type="text" name="platform_name" value="{{ old('platform_name', $settings['platform_name'] ?? 'Nexora Market') }}" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Platform Commission Rate (%)</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="50" name="commission_rate" value="{{ old('commission_rate', $settings['commission_rate'] ?? '10.00') }}" required
                                   class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary pr-8">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-on-surface-variant">%</span>
                        </div>
                        <span class="text-[10px] text-outline block mt-0.5">Applied automatically to store orders upon checkout.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Return/Refund Request Window (Days)</label>
                        <input type="number" min="1" max="60" name="return_window_days" value="{{ old('return_window_days', $settings['return_window_days'] ?? '7') }}" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <span class="text-[10px] text-outline block mt-0.5">Days from parcel delivery buyer can initiate a claim.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Customer Support Email</label>
                        <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? 'support@nexoramarket.store') }}" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Customer Support Hotline</label>
                        <input type="text" name="support_phone" value="{{ old('support_phone', $settings['support_phone'] ?? '09170000000') }}" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    </div>

                    <div class="pt-2 border-t border-outline-variant">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="checkbox" name="maintenance_mode" value="1" id="maintenance_mode"
                                   {{ (!empty($settings['maintenance_mode']) && $settings['maintenance_mode'] === '1') ? 'checked' : '' }}
                                   class="rounded text-primary focus:ring-primary">
                            <label for="maintenance_mode" class="text-xs text-on-surface font-semibold cursor-pointer">
                                Enable Maintenance Notice
                            </label>
                        </div>
                        <textarea name="maintenance_message" rows="2" placeholder="Optional notice banner for system updates..."
                                  class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">{{ old('maintenance_message', $settings['maintenance_message'] ?? '') }}</textarea>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Save Platform Configuration
                    </button>
                </form>
            </div>

            {{-- Right Column: Broadcast Announcements --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary">campaign</span>
                        <span>Broadcast Announcements & Notices</span>
                    </h2>
                    <span class="text-xs text-on-surface-variant font-medium">{{ $announcements->total() }} published</span>
                </div>

                @if($announcements->isEmpty())
                    <div class="p-12 text-center flex flex-col items-center justify-center flex-1">
                        <span class="material-symbols-outlined text-[48px] text-outline mb-3">announcement</span>
                        <h3 class="font-headline-sm text-base font-bold text-on-surface">No announcements posted</h3>
                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">Post updates, policy revisions, or holiday delivery schedules to platform users.</p>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant flex-1">
                        @foreach($announcements as $announcement)
                            <div class="py-4 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div class="space-y-1.5 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-bold text-sm text-on-surface">{{ $announcement->title }}</h3>
                                        @if(!empty($announcement->target_roles))
                                            @foreach($announcement->target_roles as $target)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary capitalize">
                                                    {{ str_replace('_', ' ', $target) }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface">
                                                All Users
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-on-surface-variant leading-relaxed whitespace-pre-line">{{ $announcement->body }}</p>
                                    <div class="flex items-center gap-3 text-[10px] text-outline pt-1">
                                        <span>Author: {{ $announcement->postedBy?->full_name ?? 'Administrator' }}</span>
                                        <span>•</span>
                                        <span>Published: {{ $announcement->published_at?->format('M d, Y h:i A') ?? $announcement->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('admin.settings.announcements.destroy', $announcement) }}"
                                      onsubmit="return confirm('Delete announcement \'{{ addslashes($announcement->title) }}\'?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-xl transition-colors" title="Delete">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($announcements->hasPages())
                        <div class="pt-4 border-t border-outline-variant">
                            {{ $announcements->links() }}
                        </div>
                    @endif
                @endif
            </div>

        </div>

    </div>

    {{-- Post Announcement Modal --}}
    <div x-show="announcementModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
         @keydown.escape.window="announcementModalOpen = false">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 shadow-xl"
             @click.outside="announcementModalOpen = false">
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Post Marketplace Announcement</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                Broadcast system notices, maintenance windows, or policy updates to specific user groups.
            </p>

            <form action="{{ route('admin.settings.announcements.store') }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Announcement Title <span class="text-error">*</span></label>
                    <input type="text" name="title" required placeholder="e.g. Scheduled Holiday Dispatch Cutoff, Updated Commission Terms..."
                           class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Target Roles (Leave unchecked for All)</label>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="buyer" class="rounded text-primary focus:ring-primary">
                            <span>Buyers</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="seller" class="rounded text-primary focus:ring-primary">
                            <span>Sellers / Merchants</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="sorting_center" class="rounded text-primary focus:ring-primary">
                            <span>Logistics Sorting Hubs</span>
                        </label>
                        <label class="flex items-center gap-2 p-2 rounded-xl bg-surface-container cursor-pointer">
                            <input type="checkbox" name="target_roles[]" value="courier" class="rounded text-primary focus:ring-primary">
                            <span>Delivery Riders</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Announcement Message Body <span class="text-error">*</span></label>
                    <textarea name="body" rows="4" required placeholder="Write the details of the announcement..."
                              class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="announcementModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90">
                        Publish Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
