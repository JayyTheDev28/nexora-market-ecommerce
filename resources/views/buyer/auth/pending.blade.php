@extends('layouts.app')

@section('title', 'Application Submitted — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-24 md:py-32">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">
        <div class="max-w-xl mx-auto bg-surface-container-lowest rounded-3xl shadow-xl p-10 md:p-16 text-center flex flex-col items-center gap-6">

            <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[40px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
            </div>

            <h1 class="font-headline-lg text-headline-lg text-on-surface">Application Submitted</h1>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                Your registration has been submitted successfully.
            </p>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                Please wait while an administrator reviews your application.
            </p>

            <p class="font-body-md text-body-md text-on-surface-variant max-w-sm">
                You will receive an email regarding the result of your application.
            </p>

            <a
                href="{{ url('/') }}"
                class="mt-4 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-8 py-4 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 hover:scale-105 transition-all">
                Back to Home
            </a>

        </div>
    </div>
</div>
@endsection
