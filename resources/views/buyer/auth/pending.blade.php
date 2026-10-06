@extends('layouts.app')

@section('title', 'Application Submitted — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-14 md:py-20">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">
        <div class="max-w-xl mx-auto bg-surface-container-lowest rounded-3xl shadow-xl p-8 md:p-12 text-center flex flex-col items-center gap-6">

            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[30px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
            </div>

            <h1 class="font-headline-lg text-headline-lg text-on-surface">Application Submitted</h1>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                Thanks, {{ auth()->user()->first_name }} — your registration has been submitted successfully.
            </p>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                Please wait while an administrator reviews your application.
            </p>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                You will receive an email at <span class="font-semibold text-on-surface">{{ auth()->user()->email }}</span> regarding the result of your application.
            </p>

            <a
                href="{{ url('/') }}"
                class="mt-2 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 hover:scale-105 transition-all">
                Back to Home
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface hover:underline">
                    Log Out
                </button>
            </form>

        </div>
    </div>
</div>
@endsection
