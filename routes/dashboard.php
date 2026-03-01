<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/runpod/dashboard/{instance?}', function (?string $instance = null) {
        return view('runpod::dashboard', ['instance' => $instance ?? 'default']);
    })->name('runpod.dashboard');
});
