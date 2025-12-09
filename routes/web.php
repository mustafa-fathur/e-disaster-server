<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

// Public
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Authenticated settings (shared for all roles)
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

// Admin routes with prefix
Route::prefix('admin')->middleware(['auth', 'active', 'admin'])->group(function () {
    // Main Features
    Volt::route('dashboard', 'admin.dashboard')->name('admin.dashboard');
    Volt::route('user', 'admin.user')->name('admin.user');
    Volt::route('volunteer', 'admin.volunteer')->name('admin.volunteer');
    Volt::route('officer', 'admin.officer')->name('admin.officer');
    Volt::route('disaster', 'admin.disaster')->name('admin.disaster');
    
    // Disaster detail sections
    Volt::route('disaster/{disaster}', 'admin.disaster.identity')->name('admin.disaster.identity');
    Volt::route('disaster/{disaster}/report', 'admin.disaster.report')->name('admin.disaster.report');
    Volt::route('disaster/{disaster}/victim', 'admin.disaster.victim')->name('admin.disaster.victim');
    Volt::route('disaster/{disaster}/aid', 'admin.disaster.aid')->name('admin.disaster.aid');
});

// Staff routes (officer and volunteer) with prefix
Route::prefix('staff')->middleware(['auth', 'active', 'officer_or_volunteer'])->group(function () {
    Volt::route('dashboard', 'staff.dashboard')->name('staff.dashboard');
});

require __DIR__.'/auth.php';
