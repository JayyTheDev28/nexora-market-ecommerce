@extends('layouts.app')

@section('title', 'Admin Account Settings — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-admin-nav active="account" />

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
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Administrator Profile & Security</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Update your administrative credentials, primary contact email, and security password.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Profile Information Card --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">person</span>
                    <span>Administrator Profile Details</span>
                </h2>

                <form action="{{ route('admin.account.profile') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">First Name <span class="text-error">*</span></label>
                            <input type="text" name="first_name" value="{{ old('first_name', $admin->first_name) }}" required
                                   class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Last Name <span class="text-error">*</span></label>
                            <input type="text" name="last_name" value="{{ old('last_name', $admin->last_name) }}" required
                                   class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Email Address <span class="text-error">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $admin->email) }}" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <span class="text-[10px] text-outline block mt-0.5">Used for administrative authentication and critical system notices.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Contact Mobile Number <span class="text-error">*</span></label>
                        <input type="text" name="contact_number" value="{{ old('contact_number', $admin->contact_number) }}" required placeholder="09170000000"
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <span class="text-[10px] text-outline block mt-0.5">Philippine mobile format (e.g., 09170000000).</span>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- Security & Password Card --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">lock</span>
                    <span>Update Administrative Password</span>
                </h2>

                <form action="{{ route('admin.account.password') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Current Password <span class="text-error">*</span></label>
                        <input type="password" name="current_password" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">New Password <span class="text-error">*</span></label>
                        <input type="password" name="password" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <span class="text-[10px] text-outline block mt-0.5">Min 8 characters, with letters, mixed case, numbers, and symbols.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Confirm New Password <span class="text-error">*</span></label>
                        <input type="password" name="password_confirmation" required
                               class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-surface-container-high text-on-surface hover:bg-surface-container-highest font-label-md text-xs font-semibold transition-all">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>
</div>
@endsection
