<?php

use Illuminate\Support\Facades\Route;

Route::middleware(config('runpod.dashboard.middleware', ['web', 'can:viewRunpod']))->group(function () {
    Route::livewire('/runpod/dashboard/{instance?}', 'runpod::livewire.runpod.runpod-dashboard')
        ->name('runpod.dashboard');
});
