@extends('layouts.app')

@section('title', 'Sign In — Nexora Market')

@section('content')
<div
    x-data="loginForm()"
    class="w-full flex flex-col md:flex-row h-[calc(100vh-5rem)] bg-surface-container-lowest">

    <x-brand-panel caption="Sign in to track orders, manage your wishlist, and check out faster." />

    {{-- RIGHT — form column. Content is short enough it usually won't need to
         scroll, but overflow-y-auto is kept for consistency with /register
         and for smaller viewports. --}}
    <div class="flex-1 h-full overflow-y-auto flex items-center">
        <div class="max-w-md mx-auto w-full px-6 md:px-10 py-10">

            <h1 class="font-display-lg text-headline-lg md:text-3xl text-on-surface">Welcome Back</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-2">
                Sign in to continue to your Nexora account.
            </p>

            {{-- No action yet — frontend only. Authentication comes in a later phase. --}}
            <form class="flex flex-col gap-6 mt-8 bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-sm p-8" @submit.prevent="submitForm()">

                <div class="flex flex-col gap-2">
                    <label for="email" class="font-label-md text-label-md text-on-surface-variant">Email Address</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">mail</span>
                        <input type="email" id="email" name="email" required placeholder="jane.doe@example.com"
                            class="w-full rounded-xl border border-outline-variant pl-11 pr-4 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <label for="password" class="font-label-md text-label-md text-on-surface-variant">Password</label>
                        <a href="#" class="font-label-md text-label-md text-primary hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required placeholder="••••••••"
                            class="w-full rounded-xl border border-outline-variant pl-11 pr-11 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant"
                            aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[20px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                    <span class="font-body-sm text-body-sm text-on-surface-variant">Keep me signed in</span>
                </label>

                <button
                    type="submit"
                    class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md text-lg px-8 py-4 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 hover:scale-[1.01] transition-all">
                    Sign In
                    <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </button>

                <p class="text-center font-body-sm text-body-sm text-on-surface-variant">
                    Don't have an account?
                    <a href="{{ url('/register') }}" class="text-primary font-label-md hover:underline">Create one</a>
                </p>

            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function loginForm() {
        return {
            showPassword: false,
            // No backend/auth yet — this is a frontend-only placeholder.
            submitForm() {
                // Intentionally a no-op for now. Authentication is a later phase.
            },
        };
    }
</script>
@endpush

@endsection
