<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Buyer registration — frontend only for now (no controller, no DB, no auth).
// Form has no action; submission handling comes in a later phase.
Route::get('/register', function () {
    return view('buyer.auth.register', ['hideFooter' => true]);
})->name('buyer.register');

// Static preview of the pending-approval screen the buyer will land on
// after submitting — not yet wired to an actual form submission.
Route::get('/register/pending', function () {
    return view('buyer.auth.pending');
})->name('buyer.pending');

// Buyer login — frontend only for now (no controller, no auth backend).
// Submit is currently a no-op; wiring this up is a later phase.
Route::get('/login', function () {
    return view('buyer.auth.login', ['hideFooter' => true]);
})->name('login');
